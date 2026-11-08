import { test, expect } from '@playwright/test';

test.describe('Pengujian Registrasi Akun SIAP OSN (https://mrcln0305.xyz/)', () => {

  test('TC01: Registrasi akun baru dengan data spesifik (testqa5)', async ({ page }) => {
    // 1. Akses halaman utama
    await page.goto('https://mrcln0305.xyz/');

    // 2. Klik tombol "Daftar Gratis" pada navbar/halaman
    const registerButton = page.getByRole('link', { name: 'Daftar Gratis' }).first();
    await registerButton.click();

    // 3. Pastikan berada di URL /register
    await expect(page).toHaveURL(/.*register/);

    // 4. Masukkan data registrasi sesuai permintaan
    await page.locator('#nama').fill('testqa5');
    await page.locator('#email').fill('testqa5@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.locator('#konfirmasi').fill('12345678_test');

    // 5. Centang persetujuan syarat dan ketentuan
    await page.locator('#agreement').check();
    await expect(page.locator('#agreement')).toBeChecked();

    // 6. Klik tombol submit "Daftar Sekarang"
    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();

    // 7. Verifikasi respon:
    // Jika belum pernah terdaftar: diarahkan ke /login
    // Jika sudah terdaftar pada eksekusi sebelumnya: menampilkan pesan "Email sudah terdaftar."
    await page.waitForTimeout(2000);

    const isRedirectedToLogin = page.url().includes('/login');
    if (isRedirectedToLogin) {
      await expect(page).toHaveURL(/.*login/);
      console.log('✅ Akun testqa5 berhasil didaftarkan dan diarahkan ke halaman login.');
    } else {
      const duplicateError = page.getByText('Email sudah terdaftar.');
      await expect(duplicateError).toBeVisible();
      console.log('ℹ️ Akun testqa5 sudah terdaftar sebelumnya (validasi duplikat berhasil).');
    }

    // Ambil tangkapan layar hasil pengujian
    await page.screenshot({ path: 'test-results/tc01-register-testqa5.png', fullPage: true });
  });

  test('TC02: Registrasi akun baru dengan email dinamis (Memastikan alur sukses selalu lolos)', async ({ page }) => {
    const timestamp = Date.now();
    const dynamicEmail = `testqa5_${timestamp}@gmail.com`;
    const dynamicUsername = `testqa5_${timestamp}`;

    // 1. Langsung menuju halaman /register
    await page.goto('https://mrcln0305.xyz/register');

    // 2. Isi formulir
    await page.locator('#nama').fill(dynamicUsername);
    await page.locator('#email').fill(dynamicEmail);
    await page.locator('#password').fill('12345678_test');
    await page.locator('#konfirmasi').fill('12345678_test');
    await page.locator('#agreement').check();

    // 3. Submit form
    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();

    // 4. Verifikasi diarahkan ke halaman login
    await expect(page).toHaveURL(/.*login/, { timeout: 10000 });
    console.log(`✅ Registrasi berhasil untuk email: ${dynamicEmail}, redirect ke /login berhasil!`);

    // Ambil tangkapan layar
    await page.screenshot({ path: 'test-results/tc02-register-dynamic-success.png', fullPage: true });
  });

  test('TC03: Validasi konfirmasi kata sandi tidak cocok', async ({ page }) => {
    await page.goto('https://mrcln0305.xyz/register');

    await page.locator('#nama').fill('testqa5_mismatch');
    await page.locator('#email').fill('testqa5_mismatch@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.locator('#konfirmasi').fill('password_berbeda');
    await page.locator('#agreement').check();

    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();

    // Pastikan tetap di /register dan form mendeteksi ketidakcocokan
    await expect(page).toHaveURL(/.*register/);
    await page.screenshot({ path: 'test-results/tc03-password-mismatch.png', fullPage: true });
  });

});
