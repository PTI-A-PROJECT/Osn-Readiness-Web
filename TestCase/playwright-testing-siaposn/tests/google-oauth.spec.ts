import { test, expect, type Page } from '@playwright/test';

// Spec ini jalan di stack lokal (Vite dev di :5173). Endpoint API dan
// redirect Google di-mock lewat page.route(), jadi tidak butuh backend,
// kredensial Google, maupun akun di server produksi.

// Target dev server lokal, bukan produksi.
test.use({ baseURL: 'http://localhost:5173' });

const API = 'http://localhost:8000/api';
const TOKEN_KEY = 'siap_osn_token';

const userSiswa = {
  id: 1,
  name: 'Budi Siswa',
  email: 'budi@example.com',
  roles: ['siswa'],
  created_at: '2026-09-25T00:00:00Z',
};

async function mockExchange(page: Page, status: number, body: unknown) {
  await page.route(`${API}/auth/google/exchange`, (route) =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify(body),
    }),
  );
}

test.describe('tombol Google', () => {
  test('di halaman masuk mengarah ke route redirect backend', async ({ page }) => {
    await page.goto('/login');
    const tombol = page.locator('button:has-text("Masuk dengan Google")');
    await expect(tombol).toBeVisible();

    const navigasi = page.waitForRequest((req) =>
      req.url().endsWith('/auth/google/redirect'),
    );
    // Cegat navigasi keluar agar tes tidak memuat Google sungguhan.
    await page.route('**/auth/google/redirect', (route) =>
      route.fulfill({ status: 200, body: 'redirect-dicegat' }),
    );
    await tombol.click();
    const req = await navigasi;
    expect(req.url()).toBe('http://localhost:8000/auth/google/redirect');
  });

  test('di halaman daftar mengarah ke route redirect backend', async ({ page }) => {
    await page.goto('/register');
    const tombol = page.locator('button:has-text("Daftar dengan Google")');
    await expect(tombol).toBeVisible();

    const navigasi = page.waitForRequest((req) =>
      req.url().endsWith('/auth/google/redirect'),
    );
    await page.route('**/auth/google/redirect', (route) =>
      route.fulfill({ status: 200, body: 'redirect-dicegat' }),
    );
    await tombol.click();
    const req = await navigasi;
    expect(req.url()).toBe('http://localhost:8000/auth/google/redirect');
  });
});

test.describe('halaman callback /oauth/google/selesai', () => {
  test('kode sah: tukar, simpan token, hapus fragmen, lalu ke dashboard', async ({ page }) => {
    await mockExchange(page, 200, {
      message: 'Login berhasil',
      data: { user: userSiswa, token: 'tok-google-123' },
    });
    // Sesi awal kosong: fetchMe dari guard harus 401 agar tidak ada user lama.
    await page.route(`${API}/auth/me`, (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: '{"message":"Unauthenticated."}' }),
    );

    await page.goto('/oauth/google/selesai#code=kode-sekali-pakai');

    await expect(page).toHaveURL(/\/siswa\/?$|\/siswa\/dashboard/, { timeout: 10_000 });

    const token = await page.evaluate((k) => localStorage.getItem(k), TOKEN_KEY);
    expect(token).toBe('tok-google-123');

    // Fragmen tidak boleh tersisa di riwayat/address bar setelah halaman berpindah.
    expect(page.url()).not.toContain('code=');
    expect(page.url()).not.toContain('#');
  });

  test('fragmen dihapus segera saat halaman selesai dimuat (sebelum tukar selesai)', async ({ page }) => {
    // Tahan respons exchange supaya keadaan "memproses" terlihat.
    const { promise: ditahan, resolve: lepas } = Promise.withResolvers<void>();
    await page.route(`${API}/auth/google/exchange`, async (route) => {
      await ditahan;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'OK', data: { user: userSiswa, token: 'tok-x' } }),
      });
    });

    await page.goto('/oauth/google/selesai#code=kode-rahasia');
    await expect(page.locator('h1')).toHaveText('Menyelesaikan login Google');
    expect(page.url()).not.toContain('code=');
    expect(page.url()).not.toContain('kode-rahasia');

    lepas();
  });

  test('fragmen error google_taut menampilkan pesan akun tidak digabung otomatis', async ({ page }) => {
    await page.goto('/oauth/google/selesai#error=google_taut');

    await expect(page.locator('h1')).toHaveText('Login Google tidak selesai');
    await expect(page.getByText('Masuk dengan kata sandi', { exact: false })).toBeVisible();
    expect(page.url()).not.toContain('error=');
  });

  test('kode exchange sudah dipakai (401 KODE_GOOGLE_TIDAK_VALID) menampilkan pesan spesifik', async ({ page }) => {
    await mockExchange(page, 401, {
      message: 'Kode tidak sah atau sudah kedaluwarsa',
      kode: 'KODE_GOOGLE_TIDAK_VALID',
    });

    await page.goto('/oauth/google/selesai#code=kode-pakai');

    await expect(page.locator('h1')).toHaveText('Login Google tidak selesai');
    await expect(page.getByText('Kode login tidak sah atau sudah kedaluwarsa')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Coba Google lagi' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Masuk dengan email' })).toBeVisible();
    // Tidak boleh pindah ke dashboard saat gagal.
    await expect(page).toHaveURL(/\/oauth\/google\/selesai$/);
  });

  test('exchange gagal jaringan/500 menampilkan pesan umum, tanpa token tersimpan', async ({ page }) => {
    await mockExchange(page, 500, { message: 'Server error' });

    await page.goto('/oauth/google/selesai#code=kode-apa-saja');

    await expect(page.locator('h1')).toHaveText('Login Google tidak selesai');
    await expect(page.getByText('Login Google gagal. Masuk dengan email dan kata sandi')).toBeVisible();

    const token = await page.evaluate((k) => localStorage.getItem(k), TOKEN_KEY);
    expect(token).toBeNull();
  });

  test('tanpa fragmen sama sekali menampilkan keadaan gagal, bukan memuat selamanya', async ({ page }) => {
    await page.goto('/oauth/google/selesai');
    await expect(page.locator('h1')).toHaveText('Login Google tidak selesai', { timeout: 5_000 });
  });
});
