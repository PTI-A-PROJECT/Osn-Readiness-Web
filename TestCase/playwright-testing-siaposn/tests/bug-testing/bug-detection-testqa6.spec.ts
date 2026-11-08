import { test, expect } from '@playwright/test';
import * as fs from 'fs';

/**
 * Suite Pengujian Otomasi Deteksi Bug (Defect Detection Suite)
 * Menggunakan Akun Siswa: testqa6@gmail.com / test_12345678
 * Target Aplikasi: https://mrcln0305.xyz/
 */

test.describe.configure({ mode: 'serial' });

test.describe('Suite Deteksi & Verifikasi Bug SIAP OSN (testqa6@gmail.com)', () => {
  const baseUrl = 'https://mrcln0305.xyz/';
  const authFile = 'auth-testqa6.json';

  test.use({ storageState: authFile });

  // =========================================================================
  // BUG-01: Information Disclosure - Kebocoran Model Eloquent Laravel
  // =========================================================================
  test('BUG-01: [Severity: High] Information Disclosure kebocoran Model Eloquent pada invalid ID latihan & simulasi', async ({ page }) => {
    // 1. Cek pada /siswa/latihan/9999
    await page.goto(`${baseUrl}siswa/latihan/9999`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const latihanText = await page.locator('main').first().innerText();
    console.log('[BUG-01] Respon latihan 9999:\n', latihanText);

    const hasQuizLeak = latihanText.includes('App\\Models\\Quiz') || latihanText.includes('No query results for model');
    await page.screenshot({ path: 'results/screenshots/bug-01-leak-latihan.png', fullPage: true });
    expect(hasQuizLeak).toBe(true);

    // 2. Cek pada /siswa/simulasi/ujian/9999
    await page.goto(`${baseUrl}siswa/simulasi/ujian/9999`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const simulasiText = await page.locator('main').first().innerText();
    console.log('[BUG-01] Respon simulasi 9999:\n', simulasiText);

    const hasSimulasiLeak = simulasiText.includes('App\\Models\\Simulasi') || simulasiText.includes('No query results for model');
    await page.screenshot({ path: 'results/screenshots/bug-01-leak-simulasi.png', fullPage: true });
    expect(hasSimulasiLeak).toBe(true);

    // 3. Cek pada /siswa/simulasi/hasil/9999
    await page.goto(`${baseUrl}siswa/simulasi/hasil/9999`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('text=No query results', { timeout: 10000 }).catch(() => {});
    const hasilText = await page.locator('main').first().innerText();
    console.log('[BUG-01] Respon hasil 9999:\n', hasilText);

    const hasHasilLeak = hasilText.includes('App\\Models\\HasilSimulasi') || hasilText.includes('No query results for model');
    await page.screenshot({ path: 'results/screenshots/bug-01-leak-hasil.png', fullPage: true });
    expect(hasHasilLeak).toBe(true);

    // 4. Cek pada /siswa/simulasi/hasil/9999/pembahasan
    await page.goto(`${baseUrl}siswa/simulasi/hasil/9999/pembahasan`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('text=No query results', { timeout: 10000 }).catch(() => {});
    const pembahasanText = await page.locator('main').first().innerText();
    console.log('[BUG-01] Respon pembahasan 9999:\n', pembahasanText);

    const hasPembahasanLeak = pembahasanText.includes('App\\Models\\HasilSimulasi') || pembahasanText.includes('No query results for model');
    await page.screenshot({ path: 'results/screenshots/bug-01-leak-pembahasan.png', fullPage: true });
    expect(hasPembahasanLeak).toBe(true);
  });

  // =========================================================================
  // BUG-02: Fitur "Ubah Kata Sandi" belum aktif di sisi server
  // =========================================================================
  test('BUG-02: [Severity: High] Fitur "Ubah Kata Sandi" menampilkan "Penyimpanan ke server belum aktif"', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/profil`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    // Buka mode ubah kata sandi
    await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
    await page.waitForTimeout(500);

    // Isi kata sandi
    await page.locator('#pw-now').fill('test_12345678');
    await page.locator('#pw-new').fill('test_12345678_new');
    await page.locator('#pw-confirm').fill('test_12345678_new');

    let apiCalled = false;
    page.on('request', req => {
      if (req.url().includes('password') || req.url().includes('sandi')) {
        apiCalled = true;
      }
    });

    await page.getByRole('button', { name: 'Simpan' }).click();
    await page.waitForTimeout(1500);

    const sec2Text = await page.locator('section:has-text("02 · KEAMANAN")').first().innerText();
    console.log('[BUG-02] Respon form ubah sandi:\n', sec2Text);

    const hasIncompleteMsg = sec2Text.includes('Penyimpanan ke server belum aktif');
    await page.screenshot({ path: 'results/screenshots/bug-02-password-server-inactive.png', fullPage: true });

    expect(hasIncompleteMsg).toBe(true);
    expect(apiCalled).toBe(false);
  });

  // =========================================================================
  // BUG-03: Data Asal Sekolah dan Kelas Diabaikan saat Simpan Profil (Data Loss)
  // =========================================================================
  test('BUG-03: [Severity: High] Data input "Asal Sekolah" dan "Kelas" diabaikan pada payload update profil', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/profil`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    await page.getByRole('button', { name: /Ubah Profil/i }).click();
    await page.waitForTimeout(500);

    await page.locator('#sekolah').fill('SMA Negeri 1 Malang');
    await page.locator('#kelas').fill('11');

    let interceptedPayload: any = null;
    page.on('request', req => {
      if (req.url().includes('/api/auth/profile') && req.method() === 'PUT') {
        interceptedPayload = req.postDataJSON();
      }
    });

    await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
    await page.waitForTimeout(2000);

    console.log('[BUG-03] Intercepted payload PUT /api/auth/profile:', interceptedPayload);
    await page.screenshot({ path: 'results/screenshots/bug-03-profile-data-loss.png', fullPage: true });

    // Verifikasi Bug: payload TIDAK mengandung field sekolah maupun kelas
    expect(interceptedPayload).not.toBeNull();
    expect(interceptedPayload.sekolah).toBeUndefined();
    expect(interceptedPayload.kelas).toBeUndefined();
  });

  // =========================================================================
  // BUG-04: Tombol "Ganti foto profil" Dead Button
  // =========================================================================
  test('BUG-04: [Severity: Medium] Tombol "Ganti foto profil" tidak memiliki aksi atau file picker', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/profil`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    const gantiFotoBtn = page.getByRole('button', { name: 'Ganti foto profil' });
    await expect(gantiFotoBtn).toBeVisible();

    await gantiFotoBtn.click();
    await page.waitForTimeout(1000);

    const fileInputs = await page.locator('input[type="file"]').count();
    const modalVisible = await page.locator('.modal, [role="dialog"]').isVisible();

    console.log(`[BUG-04] fileInputs: ${fileInputs}, modalVisible: ${modalVisible}`);
    await page.screenshot({ path: 'results/screenshots/bug-04-dead-button-ganti-foto.png', fullPage: true });

    expect(fileInputs).toBe(0);
    expect(modalVisible).toBe(false);
  });

  // =========================================================================
  // BUG-05: Tombol "Kumpulkan yang Kosong" Hanya Menutup Modal Tanpa Submit ke Server
  // =========================================================================
  test('BUG-05: [Severity: High] Tombol "Kumpulkan yang Kosong" tidak mengirim request submit ke server', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/simulasi/ujian/1`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Navigasi ke nomor terakhir
    await page.locator('button.nomer:has-text("30")').click();
    await page.waitForTimeout(500);

    // Buka modal konfirmasi kumpulkan
    await page.getByRole('button', { name: /Selesai/i }).click();
    await page.waitForTimeout(1000);

    const modal = page.locator('.modal, [role="dialog"]');
    await expect(modal).toBeVisible();

    let submitApiCalled = false;
    page.on('request', req => {
      if (req.method() === 'POST' && (req.url().includes('selesai') || req.url().includes('submit') || req.url().includes('kumpulkan'))) {
        submitApiCalled = true;
      }
    });

    // Klik tombol Kumpulkan yang Kosong
    const ghostBtn = page.locator('button.mbtn-ghost');
    await ghostBtn.click();
    await page.waitForTimeout(2000);

    console.log(`[BUG-05] Current URL: ${page.url()}, submitApiCalled: ${submitApiCalled}`);
    await page.screenshot({ path: 'results/screenshots/bug-05-unhandled-kumpulkan-kosong.png', fullPage: true });

    // Verifikasi Bug: tidak ada API submit yang dipanggil dan user tetap berada di ruang ujian
    expect(submitApiCalled).toBe(false);
    expect(page.url()).toContain('/siswa/simulasi/ujian/');
  });

  // =========================================================================
  // BUG-06: Validasi Registrasi Tanpa Pesan Teks Error
  // =========================================================================
  test('BUG-06: [Severity: Medium] Form registrasi tidak memunculkan pesan teks error saat data tidak valid', async ({ browser }) => {
    // Gunakan context bersih TANPA storageState agar tidak ter-redirect oleh SPA
    const unauthContext = await browser.newContext({ storageState: undefined });
    const page = await unauthContext.newPage();

    await page.goto(`${baseUrl}register`, { waitUntil: 'domcontentloaded' });
    // Tunggu SPA Vue.js merender form registrasi
    await page.locator('#nama').waitFor({ state: 'visible', timeout: 20000 });

    await page.locator('#nama').fill('Test User');
    await page.locator('#email').fill('test.invalid@example.com');
    await page.locator('#password').fill('123');
    await page.locator('#konfirmasi').fill('123');
    await page.locator('#agreement').check();

    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();
    await page.waitForTimeout(1500);

    const ariaInvalid = await page.locator('#password').getAttribute('aria-invalid');
    expect(ariaInvalid).toBe('true');

    const errorTexts = await page.locator('#password ~ span, #password ~ p, .error-message, .text-red-500, .text-red-600').allInnerTexts();
    const hasVisibleErrorText = errorTexts.some(t => t.trim().length > 0);

    console.log(`[BUG-06] ariaInvalid: ${ariaInvalid}, hasVisibleErrorText: ${hasVisibleErrorText}`);
    await page.screenshot({ path: 'results/screenshots/bug-06-register-missing-error-text.png', fullPage: true });

    await unauthContext.close();
    expect(hasVisibleErrorText).toBe(false);
  });

  // =========================================================================
  // BUG-07: Format Laporan Pemetaan Kompetensi Tanpa Header Identitas Siswa
  // =========================================================================
  test('BUG-07: [Severity: Low] Unduh laporan pemetaan menghasilkan CSV mentah tanpa metadata identitas siswa', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/pemetaan`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);

    const unduhBtn = page.getByRole('button', { name: /Unduh/i });
    const unduhCount = await unduhBtn.count();
    console.log(`[BUG-07] Jumlah tombol Unduh: ${unduhCount}`);

    if (unduhCount > 0) {
      await expect(unduhBtn.first()).toBeVisible();

      const [download] = await Promise.all([
        page.waitForEvent('download'),
        unduhBtn.first().click()
      ]);

      const downloadPath = await download.path();
      const content = fs.readFileSync(downloadPath!, 'utf-8');
      console.log('[BUG-07] Downloaded CSV content:\n', content);

      const hasStudentName = content.includes('testqa6');
      await page.screenshot({ path: 'results/screenshots/bug-07-unduh-laporan.png' });

      expect(hasStudentName).toBe(false);
    } else {
      // Jika tombol unduh tidak ditemukan, itu sendiri merupakan temuan
      console.log('[BUG-07] Tombol unduh tidak ditemukan di halaman pemetaan');
      await page.screenshot({ path: 'results/screenshots/bug-07-unduh-laporan.png', fullPage: true });
      // Skip test jika tombol tidak ada (bukan bug target ini)
      test.skip();
    }
  });

  // =========================================================================
  // BUG-08: Elemen Formulir & Tombol Tanpa Atribut Semantik Standar
  // =========================================================================
  test('BUG-08: [Severity: Low] Elemen input formulir profil dan kode referal memiliki name="" atau tanpa id', async ({ page }) => {
    await page.goto(`${baseUrl}siswa/profil`, { waitUntil: 'domcontentloaded' });
    await page.locator('#nama').waitFor({ state: 'visible', timeout: 15000 });

    const inputs = await page.$$eval('input', els => els.map(e => ({
      id: e.id,
      name: e.getAttribute('name'),
      type: e.type,
      value: e.value
    })));

    console.log('[BUG-08] Daftar input pada profil:\n', inputs);

    // Verifikasi Bug: terdapat input dengan name kosong atau null
    const emptyNameInputs = inputs.filter(i => !i.name || i.name.trim() === '');
    await page.screenshot({ path: 'results/screenshots/bug-08-missing-attributes.png' });

    expect(emptyNameInputs.length).toBeGreaterThan(0);
  });

});
