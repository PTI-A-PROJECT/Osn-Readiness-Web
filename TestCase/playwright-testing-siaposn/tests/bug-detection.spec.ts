import { test, expect } from '@playwright/test';

test.describe('Deteksi Bug & Analisis Kualitas Web (Bug Detection Suite)', () => {
  test.setTimeout(45000);

  test('BUG-01: [Severity: High] Tombol "Kumpulkan yang Kosong" berstatus disabled pada modal konfirmasi ujian', async ({ page }) => {
    // 1. Login menggunakan akun testqa5
    await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill('testqa5@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
    await page.waitForURL(/\/siswa/, { timeout: 20000 });

    // 2. Buka salah satu latihan materi
    await page.goto('https://mrcln0305.xyz/siswa/latihan/1', { waitUntil: 'domcontentloaded' });
    await page.locator('button.nomer').last().waitFor({ state: 'visible', timeout: 15000 });

    // 3. Langsung lompat ke nomor terakhir tanpa menjawab semua soal
    await page.locator('button.nomer').last().click();
    await page.waitForTimeout(500);

    // 4. Klik tombol Selesai
    await page.getByRole('button', { name: /Selesai/i }).click();
    await page.waitForTimeout(1000);

    // 5. Verifikasi isi dialog modal
    const modalText = await page.locator('.modal, [role="dialog"]').innerText();
    console.log('Teks modal terdeteksi:\n', modalText);
    expect(modalText).toContain('Kumpulkan yang Kosong');
    expect(modalText).toContain('Isi dulu, atau kumpulkan yang kosong bila mau');

    // DETEKSI BUG: Tombol "Kumpulkan yang Kosong" berstatus disabled padahal teks modal menawarkan opsi tersebut
    const btnKumpulkanKosong = page.locator('button:has-text("Kumpulkan yang Kosong")');
    const isDisabled = await btnKumpulkanKosong.isDisabled();

    console.log(`[BUG-01 DETECTED] Tombol 'Kumpulkan yang Kosong' isDisabled = ${isDisabled}`);
    await page.screenshot({ path: 'test-results/bug-01-disabled-submit-empty.png', fullPage: true });

    // Assertion bug: tombol terkunci padahal teks dialog mengizinkan
    expect(isDisabled).toBe(true);
  });

  test('BUG-02: [Severity: Medium] Direct Navigation / Page Refresh pada Simulasi menampilkan "Belum ada data tingkat"', async ({ page }) => {
    // 1. Login menggunakan akun testqa5
    await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill('testqa5@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
    await page.waitForURL(/\/siswa/, { timeout: 20000 });

    // 2. Lakukan navigasi langsung (Direct URL) ke /siswa/simulasi
    await page.goto('https://mrcln0305.xyz/siswa/simulasi', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    // 3. Cek apakah ada pesan error data tingkat gagal diinisialisasi
    const pageText = await page.innerText('body');
    const hasDataTingkatBug = pageText.includes('Belum ada data tingkat');

    console.log(`[BUG-02 DETECTED] Direct navigation memunculkan pesan error state = ${hasDataTingkatBug}`);
    await page.screenshot({ path: 'test-results/bug-02-direct-nav-tingkat.png', fullPage: true });

    if (hasDataTingkatBug) {
      console.warn('⚠️ BUG-02 Terkonfirmasi: State data tingkat tidak dimuat pada direct load.');
    }
  });

  test('BUG-03: [Severity: Medium] Validasi form registrasi tidak memunculkan pesan error teks', async ({ page }) => {
    await page.goto('https://mrcln0305.xyz/register', { waitUntil: 'domcontentloaded' });
    await page.locator('#nama').waitFor({ state: 'visible', timeout: 10000 });

    // Masukkan password terlalu pendek (< 8 karakter)
    await page.locator('#nama').fill('Tester Bug 03');
    await page.locator('#email').fill('tester.bug03@gmail.com');
    await page.locator('#password').fill('123');
    await page.locator('#konfirmasi').fill('123');
    await page.locator('#agreement').check();

    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();
    await page.waitForTimeout(1000);

    // DETEKSI BUG: input ditandai invalid (aria-invalid="true"), tapi tidak ada teks pesan error yang menjelaskan kesalahannya
    const ariaInvalid = await page.locator('#password').getAttribute('aria-invalid');
    expect(ariaInvalid).toBe('true');

    // Cari pesan error visual berupa teks di sekitar input password
    const errorMessages = await page.locator('#password ~ span, #password ~ p, .error-message, .text-red-500').allInnerTexts();
    const hasVisibleTextError = errorMessages.some((msg) => msg.trim().length > 0);

    console.log(`[BUG-03 DETECTED] Ada penanda invalid tetapi tidak ada teks error: ${!hasVisibleTextError}`);
    await page.screenshot({ path: 'test-results/bug-03-missing-validation-text.png', fullPage: true });

    expect(hasVisibleTextError).toBe(false);
  });

  test('BUG-04: [Severity: Low] Atribut "name" kosong pada seluruh input formulir Registrasi', async ({ page }) => {
    await page.goto('https://mrcln0305.xyz/register', { waitUntil: 'domcontentloaded' });
    await page.locator('#nama').waitFor({ state: 'visible', timeout: 10000 });

    // Cek atribut name pada seluruh elemen input
    const inputs = await page.locator('input').evaluateAll((els) =>
      els.map((e) => ({
        id: e.id,
        type: e.type,
        name: e.getAttribute('name'),
      }))
    );

    // Input terdeteksi memiliki name="" atau null
    const missingNameCount = inputs.filter((i) => !i.name || i.name.trim() === '').length;
    console.log(`[BUG-04 DETECTED] Jumlah input tanpa atribut name: ${missingNameCount}/${inputs.length}`);

    await page.screenshot({ path: 'test-results/bug-04-missing-name-attributes.png', fullPage: true });
    expect(missingNameCount).toBeGreaterThan(0);
  });

});
