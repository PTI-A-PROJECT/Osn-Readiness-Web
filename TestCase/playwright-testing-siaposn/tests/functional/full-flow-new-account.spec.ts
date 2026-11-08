import { test, expect, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * End-to-End Automation Test Suite untuk Akun Baru SIAP OSN:
 * Email: testqa6@gmail.com | Password: test_12345678
 *
 * Alur Pengujian:
 * 1. Registrasi Akun Baru (atau Auto-Login jika sudah terdaftar)
 * 2. Pre-Test pada Menu Pemetaan Kompetensi (30 Butir Soal)
 * 3. Baca Seluruh Materi Wajib Kabupaten/Kota (Mulai Belajar & Tandai sebagai Selesai)
 * 4. Pengerjaan Soal Latihan Setiap Materi (10 Butir Soal per Materi dengan Kunci Jawaban)
 * 5. Cek & Verifikasi Menu Riwayat Hasil
 * 6. Simulasi Seleksi OSN Tingkat Kabupaten/Kota (30 Butir Soal dengan Kunci Jawaban)
 */

interface KeyRow {
  id: string;
  materiId: string;
  tipe: string;
  key: string;
  pilihan: Record<string, string>;
}

// 1. Memuat Fixture Kunci Jawaban dari coba1/keys.psv
const keyPath = path.resolve(process.cwd(), 'coba1', 'keys.psv');
let keyRows: KeyRow[] = [];
if (fs.existsSync(keyPath)) {
  keyRows = fs
    .readFileSync(keyPath, 'utf8')
    .trim()
    .split(/\r?\n/)
    .map((line) => {
      const [id, materiId, tipe, key, pilihan] = line.split('|');
      let parsedPilihan: Record<string, string> = {};
      try {
        parsedPilihan = pilihan ? JSON.parse(pilihan) : {};
      } catch {}
      return {
        id: String(id),
        materiId: String(materiId),
        tipe: tipe || 'pilihan_ganda',
        key: key ? key.trim().toLowerCase() : '',
        pilihan: parsedPilihan,
      };
    });
}

function getChoiceSig(pilihan: Record<string, string> | undefined): string {
  if (!pilihan) return '';
  return Object.values(pilihan)
    .map((v) => String(v).trim().toLowerCase())
    .sort()
    .join('###');
}

const keyById = new Map<string, KeyRow>();
const keyBySigWithMateri = new Map<string, KeyRow>();
const keyByChoiceOnly = new Map<string, KeyRow>();

for (const row of keyRows) {
  keyById.set(row.id, row);
  const sigWithMateri = `${row.materiId}:::${getChoiceSig(row.pilihan)}`;
  if (!keyBySigWithMateri.has(sigWithMateri)) {
    keyBySigWithMateri.set(sigWithMateri, row);
  }
  const sigChoice = getChoiceSig(row.pilihan);
  if (!keyByChoiceOnly.has(sigChoice)) {
    keyByChoiceOnly.set(sigChoice, row);
  }
}

// Fungsi cerdas untuk menyelesaikan ujian (Latihan Soal Materi, Pre-Test, atau Simulasi)
async function solveExam(page: Page, questions: any[], examLabel: string) {
  const nomorButtons = page.locator('.nomer, button.nomer');
  await nomorButtons.first().waitFor({ state: 'visible', timeout: 25000 });
  const count = await nomorButtons.count();
  const total = questions.length > 0 ? questions.length : count;

  console.log(`[${examLabel}] Memulai pengerjaan ${total} butir soal...`);

  for (let index = 0; index < total; index++) {
    const item = questions[index] || {};

    if ((await nomorButtons.count()) > index) {
      await nomorButtons.nth(index).click();
      await page.waitForTimeout(200);
    }

    const sigWithMateri = `${item.materi_id || item.materiId}:::${getChoiceSig(item.pilihan_jawaban || item.pilihan)}`;
    const sigChoice = getChoiceSig(item.pilihan_jawaban || item.pilihan);

    const match =
      keyBySigWithMateri.get(sigWithMateri) ||
      keyByChoiceOnly.get(sigChoice) ||
      keyById.get(String(item.id));

    const isianTextarea = page.locator('textarea, input[type="text"].jawaban, [placeholder*="Tulis jawaban"]').first();
    const isIsian = (await isianTextarea.count()) > 0 && (await isianTextarea.isVisible());

    if (isIsian) {
      const jawabanTeks = match?.key || '10';
      await isianTextarea.fill(jawabanTeks);
    } else {
      const deployChoices: Record<string, string> = item.pilihan_jawaban || item.pilihan || {};
      let targetLetter = '';

      if (match && match.pilihan && match.key) {
        const correctText = String(match.pilihan[match.key] || '').trim().toLowerCase();
        for (const [letter, text] of Object.entries(deployChoices)) {
          if (String(text).trim().toLowerCase() === correctText) {
            targetLetter = letter.toLowerCase();
            break;
          }
        }
      }

      if (!targetLetter && match?.key) {
        targetLetter = match.key.toLowerCase();
      }

      const letterIndex = ['a', 'b', 'c', 'd', 'e'].indexOf(targetLetter);
      const options = page.locator('button.opsi, .opsi');
      await options.first().waitFor({ state: 'visible', timeout: 8000 });

      if (letterIndex >= 0 && letterIndex < (await options.count())) {
        await options.nth(letterIndex).click();
      } else {
        await options.first().click();
      }
    }

    // Debounce autosave backend
    await page.waitForTimeout(350);

    if ((index + 1) % 10 === 0 || index + 1 === total) {
      console.log(`[${examLabel}] Kemajuan: ${index + 1}/${total} soal selesai.`);
    }
  }

  // Klik tombol Selesai
  const btnSelesai = page.getByRole('button', { name: /Selesai/i });
  await btnSelesai.click();
  await page.waitForTimeout(1000);

  // Konfirmasi modal kumpulkan
  const btnKumpulkan = page.getByRole('button', { name: /Kumpulkan|Submit|Ya/i }).last();
  await btnKumpulkan.waitFor({ state: 'visible', timeout: 8000 });
  await btnKumpulkan.click();
  await page.waitForTimeout(3000);
}

test.describe('Full Flow Pengujian Siswa Baru - SIAP OSN (testqa6@gmail.com)', () => {
  test.setTimeout(600000); // 10 menit batas waktu total suite

  test('Eksekusi Alur Lengkap: Register -> Pre-Test -> Materi & Latihan -> Riwayat -> Simulasi Kabupaten', async ({ page }) => {
    const baseUrl = 'https://mrcln0305.xyz/';
    const email = 'testqa6@gmail.com';
    const password = 'test_12345678';
    const username = 'testqa6';

    // =========================================================================
    // TAHAP 1: REGISTRASI ATAU LOGIN
    // =========================================================================
    console.log('\n--- 1. Registrasi Akun testqa6 (atau Login jika telah terdaftar) ---');
    await page.goto(`${baseUrl}register`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    await page.locator('#nama').fill(username);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#konfirmasi').fill(password);
    await page.locator('#agreement').check();
    await page.getByRole('button', { name: /Daftar Sekarang/i }).click();

    await page.waitForTimeout(2000);

    // Cek jika akun sudah terdaftar sebelumnya atau langsung dialihkan
    const bodyText = await page.locator('body').innerText();
    if (bodyText.includes('Email sudah terdaftar') || !page.url().includes('/login')) {
      console.log('ℹ️ Akun testqa6 sudah terdaftar sebelumnya. Melanjutkan proses login...');
      await page.goto(`${baseUrl}login`, { waitUntil: 'domcontentloaded' });
    }

    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();

    await page.waitForURL(/\/siswa/, { timeout: 20000 });
    console.log('✅ Berhasil login dan masuk ke Dashboard Siswa!');
    await page.screenshot({ path: 'test-results/01-dashboard-siswa.png', fullPage: true });

    // =========================================================================
    // TAHAP 2: PRE-TEST DI MENU PEMETAAN KOMPETENSI
    // =========================================================================
    console.log('\n--- 2. Pengerjaan Pre-Test di Menu Pemetaan Kompetensi ---');
    await page.getByRole('link', { name: 'Pemetaan Kompetensi' }).click();
    await page.waitForURL(/\/siswa\/pemetaan/, { timeout: 15000 });
    await page.waitForTimeout(2000);

    const btnIkutiPretest = page.getByRole('button', { name: /Ikuti Pre-Test/i });
    if (await btnIkutiPretest.isVisible()) {
      await btnIkutiPretest.click();
      await page.waitForURL(/\/siswa\/pretest/, { timeout: 15000 });
      await page.waitForTimeout(1500);

      const btnMulaiPretest = page.getByRole('button', { name: 'Mulai Pre-Test' });
      await btnMulaiPretest.waitFor({ state: 'visible', timeout: 10000 });

      const pretestPromise = page.waitForResponse(
        (res) => res.url().includes('/api/pretest/') && res.request().method() === 'GET',
        { timeout: 15000 }
      ).catch(() => null);

      await btnMulaiPretest.click();
      const pretestRes = await pretestPromise;
      let pretestQuestions: any[] = [];
      if (pretestRes) {
        try {
          const pretestData = await pretestRes.json();
          pretestQuestions = (pretestData?.data?.soal ?? pretestData?.soal ?? []).map((s: any) => s.soal ?? s);
        } catch {}
      }

      await solveExam(page, pretestQuestions, 'Pre-Test Kabupaten/Kota (30 Soal)');
      await expect(page).toHaveURL(/.*siswa\/pemetaan/, { timeout: 30000 });
      console.log('✅ Pre-test 30 butir soal berhasil diselesaikan dan dinilai!');
    } else {
      console.log('ℹ️ Pre-Test sudah diselesaikan sebelumnya.');
    }

    await page.screenshot({ path: 'test-results/02-hasil-pemetaan-kompetensi.png', fullPage: true });

    // =========================================================================
    // TAHAP 3: BACA SEMUA MATERI & TANDAI SELESAI SERTA LATIHAN SOAL
    // =========================================================================
    console.log('\n--- 3. Membaca Seluruh Materi & Mengerjakan Seluruh Latihan Soal ---');
    await page.getByRole('link', { name: 'Materi' }).click();
    await page.waitForURL(/\/siswa\/materi/, { timeout: 15000 });
    await page.waitForTimeout(2000);

    // Ambil seluruh daftar judul materi pada kartu materi
    const materialCards = page.locator('article.material-card');
    const totalMateri = await materialCards.count();
    console.log(`Ditemukan ${totalMateri} modul materi pembelajaran.`);

    for (let t = 0; t < totalMateri; t++) {
      // Buka halaman materi via menu
      await page.getByRole('link', { name: 'Materi' }).click();
      await page.waitForURL(/\/siswa\/materi/, { timeout: 15000 });
      await page.waitForTimeout(1500);

      const card = page.locator('article.material-card').nth(t);
      const judulMateri = (await card.locator('h3, h4').first().innerText()).trim();
      console.log(`\n▶ [Modul ${t + 1}/${totalMateri}] ${judulMateri}`);

      // Klik tombol "Mulai belajar →" pada kartu materi
      const btnMulaiBelajar = card.locator('button').first();
      await btnMulaiBelajar.click();
      await page.waitForTimeout(2000);

      // Klik semua tombol "Tandai sebagai Selesai" yang ada pada submateri
      let tandaiBtn = page.getByRole('button', { name: /Tandai sebagai Selesai/i }).first();
      let clickCount = 0;
      while ((await tandaiBtn.count()) > 0 && (await tandaiBtn.isVisible())) {
        await tandaiBtn.click();
        clickCount++;
        await page.waitForTimeout(400);
        tandaiBtn = page.getByRole('button', { name: /Tandai sebagai Selesai/i }).first();
      }
      console.log(`   ✅ Selesai membaca & menandai ${clickCount} submateri.`);

      // Klik tombol "Mulai latihan" jika ada
      const btnLatihan = page.getByRole('button', { name: /Mulai latihan/i }).first();
      if (await btnLatihan.isVisible()) {
        const quizPromise = page.waitForResponse(
          (res) => res.url().includes('/api/quiz/') && res.url().endsWith('/mulai') && res.request().method() === 'POST',
          { timeout: 15000 }
        ).catch(() => null);

        await btnLatihan.click();
        const quizRes = await quizPromise;
        let quizQuestions: any[] = [];
        if (quizRes) {
          try {
            const quizPacket = await quizRes.json();
            const quizData = quizPacket?.data ?? quizPacket;
            quizQuestions = (quizData?.soal ?? quizData?.questions ?? []).map((s: any) => s.soal ?? s);
          } catch {}
        }

        await solveExam(page, quizQuestions, `Latihan: ${judulMateri}`);
        console.log(`   ✅ Latihan soal materi "${judulMateri}" berhasil diselesaikan!`);
        await page.screenshot({ path: `test-results/03-materi-${t + 1}-selesai.png`, fullPage: true });
        await page.waitForTimeout(2000);
      } else {
        console.log(`   ℹ️ Latihan untuk modul ini sudah diselesaikan sebelumnya.`);
      }
    }

    // =========================================================================
    // TAHAP 4: CEK MENU RIWAYAT HASIL
    // =========================================================================
    console.log('\n--- 4. Membuka dan Mengecek Menu Riwayat Hasil ---');
    await page.getByRole('link', { name: 'Riwayat Hasil' }).click();
    await page.waitForURL(/\/siswa\/riwayat/, { timeout: 15000 });
    await page.waitForTimeout(2500);

    await expect(page.getByRole('heading', { name: 'Riwayat Hasil' })).toBeVisible({ timeout: 15000 });
    console.log('✅ Menu Riwayat Hasil berhasil diakses dan menampilkan histori belajar!');
    await page.screenshot({ path: 'test-results/04-riwayat-hasil.png', fullPage: true });

    // =========================================================================
    // TAHAP 5: SIMULASI KABUPATEN/KOTA
    // =========================================================================
    console.log('\n--- 5. Pengerjaan Simulasi Seleksi OSN Tingkat Kabupaten/Kota ---');
    await page.getByRole('link', { name: 'Simulasi Seleksi' }).click();
    await page.waitForURL(/\/siswa\/simulasi/, { timeout: 15000 });
    await page.waitForTimeout(3000);

    // Klik tombol 'Pilih Tingkat' Kabupaten/Kota jika ada
    const btnPilihTingkat = page.getByRole('button', { name: 'Pilih Tingkat' });
    if (await btnPilihTingkat.isVisible()) {
      await btnPilihTingkat.click();
      await page.waitForTimeout(1500);
    }

    const btnMulaiSimulasi = page.getByRole('button', { name: /Mulai Simulasi|Lanjutkan Simulasi/i }).first();
    await expect(btnMulaiSimulasi).toBeEnabled({ timeout: 25000 });
    console.log('🎉 Syarat Simulasi Terpenuhi! Tombol Mulai Simulasi aktif.');

    const simulasiPromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/simulasi/') &&
        (res.url().endsWith('/mulai') || res.url().includes('/soal')) &&
        (res.request().method() === 'POST' || res.request().method() === 'GET'),
      { timeout: 20000 }
    ).catch(() => null);

    await btnMulaiSimulasi.click();
    await page.waitForTimeout(1000);

    // Centang persetujuan pada modal dialog jika muncul
    const dialogCheckbox = page.getByRole('dialog').getByRole('checkbox');
    if (await dialogCheckbox.isVisible()) {
      await dialogCheckbox.check();
      await page.waitForTimeout(300);
      await page.getByRole('button', { name: /Mulai Simulasi/i }).last().click();
    }

    const simulasiRes = await simulasiPromise;
    let simulasiQuestions: any[] = [];
    if (simulasiRes) {
      try {
        const simulasiPacket = await simulasiRes.json();
        const simulasiData = simulasiPacket?.data ?? simulasiPacket;
        simulasiQuestions = (simulasiData?.soal ?? simulasiData?.questions ?? []).map((s: any) => s.soal ?? s);
      } catch {}
    }

    await solveExam(page, simulasiQuestions, 'Simulasi Kabupaten/Kota (30 Soal)');

    await expect(page).toHaveURL(/.*siswa\/simulasi\/hasil/, { timeout: 30000 });
    console.log('🏆 Simulasi Seleksi Kabupaten/Kota telah berhasil diselesaikan dan dinilai!');
    await page.screenshot({ path: 'test-results/05-simulasi-kabupaten-selesai.png', fullPage: true });

    console.log('\n============================================================');
    console.log('🎉 SELURUH ALUR E2E (TESTQA6) BERHASIL DIJALANKAN DENGAN SEMPURNA! 🎉');
    console.log('============================================================\n');
  });
});
