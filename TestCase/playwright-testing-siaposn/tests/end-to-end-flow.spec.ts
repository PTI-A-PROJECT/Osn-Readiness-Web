import { test, expect } from '@playwright/test';

test.describe('E2E Flow: Registrasi, Pre-Test, hingga Pengerjaan Soal Materi', () => {

  // Tingkatkan timeout per test karena alur pengerjaan 30 soal pre-test + 10 soal materi membutuhkan waktu
  test.setTimeout(120000);

  test('Flow Lengkap: Register Akun Baru -> Login -> Pre-Test (30 Soal) -> Soal Materi (10 Soal)', async ({ page }) => {
    const timestamp = Date.now();
    const dynamicUsername = `testqa5_${timestamp}`;
    const dynamicEmail = `testqa5_${timestamp}@gmail.com`;
    const password = '12345678_test';

    // ==========================================
    // TAHAP 1: REGISTRASI AKUN BARU
    // ==========================================
    console.log('--- TAHAP 1: Registrasi Akun Baru ---');
    await page.goto('https://mrcln0305.xyz/register', { waitUntil: 'domcontentloaded' });

    await page.locator('#nama').fill(dynamicUsername);
    await page.locator('#email').fill(dynamicEmail);
    await page.locator('#password').fill(password);
    await page.locator('#konfirmasi').fill(password);
    await page.locator('#agreement').check();

    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();

    // Pastikan diarahkan ke halaman login
    await expect(page).toHaveURL(/.*login/, { timeout: 15000 });
    console.log(`✅ Registrasi berhasil untuk ${dynamicEmail}`);

    // ==========================================
    // TAHAP 2: LOGIN DENGAN AKUN BARU
    // ==========================================
    console.log('--- TAHAP 2: Login ke Sistem ---');
    await page.locator('#email').fill(dynamicEmail);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();

    // Pastikan masuk ke dashboard siswa
    await expect(page).toHaveURL(/.*siswa/, { timeout: 15000 });
    await expect(page.getByText(`Halo ${dynamicUsername}!`)).toBeVisible({ timeout: 10000 });
    console.log('✅ Login berhasil dan masuk ke dashboard siswa');

    // ==========================================
    // TAHAP 3: PENGERJAAN SOAL PRE-TEST
    // ==========================================
    console.log('--- TAHAP 3: Memulai & Mengerjakan Pre-Test ---');
    // Klik tombol Mulai Pretest di dashboard
    const btnMulaiPretest = page.getByRole('button', { name: /Mulai Pretest/i });
    await btnMulaiPretest.click();

    await expect(page).toHaveURL(/.*siswa\/pretest/);

    // Di halaman pilih tingkat, pilih Kabupaten/Kota dan klik 'Mulai Pre-Test'
    const btnMulaiTingkat = page.getByRole('button', { name: 'Mulai Pre-Test' });
    await btnMulaiTingkat.click();

    // Tunggu hingga antarmuka butir soal pretest tampil
    await page.locator('button.nomer').first().waitFor({ state: 'visible', timeout: 15000 });
    const totalSoalPretest = await page.locator('button.nomer').count();
    console.log(`Total butir soal pre-test terdeteksi: ${totalSoalPretest}`);

    // Loop menjawab semua soal pre-test
    for (let i = 1; i <= totalSoalPretest; i++) {
      await page.locator('button.nomer').nth(i - 1).click();
      await page.waitForTimeout(250);

      const opsiPilihan = page.locator('button.opsi');
      const inputIsian = page.locator('input[type="text"], textarea');

      if (await opsiPilihan.count() > 0) {
        // Pilih opsi a (pilihan pertama)
        await opsiPilihan.first().click();
      } else if (await inputIsian.count() > 0) {
        await inputIsian.first().fill('10');
      }

      if (i % 10 === 0 || i === totalSoalPretest) {
        console.log(`Pre-test: Terjawab hingga soal ${i}/${totalSoalPretest}`);
      }
    }

    // Klik tombol Selesai pada soal terakhir
    const btnSelesaiPretest = page.getByRole('button', { name: /Selesai/i });
    await btnSelesaiPretest.click();

    // Konfirmasi modal: klik 'Kumpulkan Jawaban'
    const btnKumpulkanPretest = page.getByRole('button', { name: 'Kumpulkan Jawaban' });
    await btnKumpulkanPretest.waitFor({ state: 'visible', timeout: 5000 });
    await btnKumpulkanPretest.click();

    // Verifikasi diarahkan ke halaman hasil pemetaan kompetensi
    await expect(page).toHaveURL(/.*siswa\/pemetaan/, { timeout: 15000 });
    await expect(page.getByText(/HASIL PRE-TEST/i)).toBeVisible();
    console.log('✅ Pre-test berhasil dikumpulkan dan hasil pemetaan kompetensi ditampilkan.');

    await page.screenshot({ path: 'test-results/e2e-pretest-selesai.png', fullPage: true });

    // ==========================================
    // TAHAP 4: PENGERJAAN SOAL MATERI
    // ==========================================
    console.log('--- TAHAP 4: Navigasi ke Materi dan Mengerjakan Soal Materi ---');
    // Buka menu Materi dari navbar
    await page.getByRole('link', { name: 'Materi' }).click();
    await expect(page).toHaveURL(/.*siswa\/materi/);

    // Pilih salah satu topik materi (misalnya: Aljabar Boolean & Teori Himpunan)
    const topikMateri = page.getByRole('button', { name: /Aljabar Boolean & Teori Himpunan/i });
    await topikMateri.click();

    // Pastikan masuk ke detail bacaan materi dan temukan tombol 'Mulai Latihan →'
    const btnMulaiLatihan = page.getByRole('button', { name: /Mulai Latihan/i });
    await expect(btnMulaiLatihan).toBeVisible();
    await btnMulaiLatihan.click();

    // Verifikasi URL mengarah ke latihan soal materi (/siswa/latihan/...)
    await expect(page).toHaveURL(/.*siswa\/latihan/, { timeout: 15000 });
    console.log('Masuk ke halaman pengerjaan latihan soal materi.');

    // Tunggu antarmuka nomor soal materi
    await page.locator('button.nomer').first().waitFor({ state: 'visible', timeout: 10000 });
    const totalSoalMateri = await page.locator('button.nomer').count();
    console.log(`Total butir soal materi: ${totalSoalMateri}`);

    // Loop menjawab semua butir soal materi
    for (let j = 1; j <= totalSoalMateri; j++) {
      await page.locator('button.nomer').nth(j - 1).click();
      await page.waitForTimeout(250);

      const opsiMateri = page.locator('button.opsi');
      const isianMateri = page.locator('input[type="text"], textarea');

      if (await opsiMateri.count() > 0) {
        await opsiMateri.first().click();
      } else if (await isianMateri.count() > 0) {
        await isianMateri.first().fill('5');
      }

      console.log(`Soal Materi: Terjawab ${j}/${totalSoalMateri}`);
    }

    // Klik Selesai pada soal materi
    const btnSelesaiMateri = page.getByRole('button', { name: /Selesai/i });
    await btnSelesaiMateri.click();

    // Konfirmasi modal kumpulkan latihan
    const btnKumpulkanMateri = page.getByRole('button', { name: 'Kumpulkan Jawaban' });
    await btnKumpulkanMateri.waitFor({ state: 'visible', timeout: 5000 });
    await btnKumpulkanMateri.click();

    // Tunggu hingga proses penilaian latihan materi selesai
    await page.waitForTimeout(3000);
    console.log('✅ Pengerjaan soal materi berhasil diselesaikan dan dikumpulkan!');

    // Ambil tangkapan layar akhir
    await page.screenshot({ path: 'test-results/e2e-soal-materi-selesai.png', fullPage: true });
  });

  test('Latihan Soal Materi dengan Akun testqa5', async ({ page }) => {
    // Skenario langsung login dengan akun testqa5 yang sudah terdaftar & pre-test selesai
    await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill('testqa5@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();

    await expect(page).toHaveURL(/.*siswa/, { timeout: 15000 });

    // Ke halaman Materi
    await page.getByRole('link', { name: 'Materi' }).click();
    await expect(page).toHaveURL(/.*siswa\/materi/);

    // Pilih materi Graf & Geometri
    await page.getByRole('button', { name: /Graf & Geometri/i }).click();

    // Klik tombol Mulai Latihan
    const btnLatihan = page.getByRole('button', { name: /Mulai Latihan/i });
    await expect(btnLatihan).toBeVisible();
    await btnLatihan.click();

    await expect(page).toHaveURL(/.*siswa\/latihan/);

    // Jawab 3 soal pertama sebagai sample
    const nomorCount = await page.locator('button.nomer').count();
    const sampleLimit = Math.min(3, nomorCount);

    for (let k = 1; k <= sampleLimit; k++) {
      await page.locator('button.nomer').nth(k - 1).click();
      await page.waitForTimeout(250);

      const opsi = page.locator('button.opsi');
      if (await opsi.count() > 0) {
        await opsi.first().click();
      }
    }

    console.log(`✅ Berhasil membuka dan menjawab sample soal latihan materi untuk testqa5.`);
    await page.screenshot({ path: 'test-results/materi-testqa5-sample.png', fullPage: true });
  });

});
