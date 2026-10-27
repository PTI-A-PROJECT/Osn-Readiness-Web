import { test, expect, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

interface KeyRow {
  id: string;
  materiId: string;
  tipe: string;
  key: string;
  pilihan: Record<string, string>;
}

// 1. Membaca file fixture soal & kunci jawaban dari coba1/keys.psv
const keyPath = path.join(__dirname, '..', 'coba1', 'keys.psv');
const keyRows: KeyRow[] = fs
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

// Normalisasi opsi jawaban menjadi signature unik
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

console.log(`Berhasil memuat ${keyRows.length} baris kunci jawaban dari coba1/keys.psv.`);

// Fungsi cerdas untuk menyelesaikan ujian (Latihan Soal Materi atau Simulasi)
async function solveExam(page: Page, questions: any[], examLabel: string) {
  const soalUrut = [...questions].sort(
    (a, b) => Number(a.urutan || 0) - Number(b.urutan || 0)
  );

  console.log(`[${examLabel}] Mengerjakan ${soalUrut.length} butir soal...`);

  for (let index = 0; index < soalUrut.length; index++) {
    const item = soalUrut[index];

    // 1. Cari kunci jawaban berdasarkan signature opsi dan materi, atau fallback ke ID
    const sigWithMateri = `${item.materi_id || item.materiId}:::${getChoiceSig(item.pilihan_jawaban || item.pilihan)}`;
    const sigChoice = getChoiceSig(item.pilihan_jawaban || item.pilihan);

    const match =
      keyBySigWithMateri.get(sigWithMateri) ||
      keyByChoiceOnly.get(sigChoice) ||
      keyById.get(String(item.id));

    // 2. Klik nomor soal pada panel navigasi nomor
    const nomorButtons = page.locator('.nomer, button.nomer');
    if ((await nomorButtons.count()) > index) {
      await nomorButtons.nth(index).click();
      await page.waitForTimeout(200);
    }

    // 3. Jawab soal
    const isianTextarea = page.locator('textarea, input[type="text"].jawaban, [placeholder*="Tulis jawaban"]').first();
    const isIsian = await isianTextarea.count() > 0 && await isianTextarea.isVisible();

    if (isIsian) {
      const jawabanTeks = match?.key || 'Jawaban';
      await isianTextarea.fill(jawabanTeks);
    } else {
      // Pilihan Ganda: Temukan huruf opsi yang sesuai di deploy
      const deployChoices: Record<string, string> =
        item.pilihan_jawaban || item.pilihan || {};

      let targetLetter = '';

      if (match && match.pilihan && match.key) {
        const correctText = String(match.pilihan[match.key] || '')
          .trim()
          .toLowerCase();

        // Cari huruf opsi pada deploy yang nilainya cocok dengan correctText
        for (const [letter, text] of Object.entries(deployChoices)) {
          if (String(text).trim().toLowerCase() === correctText) {
            targetLetter = letter.toLowerCase();
            break;
          }
        }
      }

      // Jika tidak ditemukan dari pencocokan nilai, gunakan kunci huruf langsung
      if (!targetLetter && match?.key) {
        targetLetter = match.key.toLowerCase();
      }

      // Tentukan indeks tombol opsi (a -> 0, b -> 1, c -> 2, d -> 3, e -> 4)
      const letterIndex = ['a', 'b', 'c', 'd', 'e'].indexOf(targetLetter);
      const options = page.locator('button.opsi, .opsi');
      await options.first().waitFor({ state: 'visible', timeout: 5000 });

      if (letterIndex >= 0 && letterIndex < (await options.count())) {
        await options.nth(letterIndex).click();
      } else {
        // Fallback: klik opsi yang mengandung huruf
        const optByText = page.locator(`button.opsi:has-text("${targetLetter}.")`);
        if ((await optByText.count()) > 0) {
          await optByText.first().click();
        } else {
          await options.first().click();
        }
      }
    }

    // Debounce autosave backend
    await page.waitForTimeout(500);

    if ((index + 1) % 5 === 0 || index + 1 === soalUrut.length) {
      console.log(`[${examLabel}] Terjawab: ${index + 1}/${soalUrut.length}`);
    }
  }

  // Klik tombol Selesai
  const btnSelesai = page.getByRole('button', { name: /Selesai/i });
  await btnSelesai.click();
  await page.waitForTimeout(1000);

  // Klik tombol konfirmasi Kumpulkan Jawaban
  const btnKumpulkan = page.getByRole('button', { name: /Kumpulkan|Submit/i }).last();
  await btnKumpulkan.waitFor({ state: 'visible', timeout: 5000 });
  await btnKumpulkan.click();

  await page.waitForTimeout(3000);
  console.log(`[${examLabel}] Berhasil dikumpulkan.`);
}

test.describe('Alur Penuh: Tandai Selesai Seluruh Materi Wajib, Pengerjaan Soal Latihan & Simulasi', () => {
  test.setTimeout(300000); // 5 menit batas waktu keseluruhan

  test('Selesaikan seluruh materi wajib (Tandai Selesai + Kunci Jawaban) dan buka Simulasi', async ({ page }) => {
    // ==========================================
    // 1. LOGIN KE SISTEM
    // ==========================================
    console.log('--- 1. Login dengan akun testqa5 ---');
    await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill('testqa5@gmail.com');
    await page.locator('#password').fill('12345678_test');
    await page.getByRole('button', { name: 'Masuk ke Akun' }).click();

    await page.waitForURL(/\/siswa(?:$|\/)/, { timeout: 20000 });
    console.log('✅ Berhasil masuk ke dashboard siswa.');

    // ==========================================
    // 2. PENGERJAAN SELURUH MATERI WAJIB
    // ==========================================
    console.log('--- 2. Memproses Seluruh Materi Wajib Kabupaten/Kota ---');
    const requiredTopics = [
      { name: 'Aljabar Boolean & Teori Himpunan', regex: /Aljabar Boolean & Teori Himpunan/i },
      { name: 'Graf & Geometri', regex: /Graf & Geometri/i },
      { name: 'Kombinatorika, Model Matematis & Deret', regex: /Kombinatorika, Model Matematis & Deret/i },
    ];

    for (let t = 0; t < requiredTopics.length; t++) {
      const topic = requiredTopics[t];
      console.log(`\n▶ [Materi ${t + 1}/${requiredTopics.length}] ${topic.name}`);

      // Buka halaman materi
      await page.goto('https://mrcln0305.xyz/siswa/materi', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1500);

      // Klik topik materi
      const topicBtn = page.getByRole('button', { name: topic.regex });
      await expect(topicBtn).toBeVisible({ timeout: 10000 });
      await topicBtn.click();
      await page.waitForTimeout(1500);

      // Klik 'Tandai Selesai' di tampilan materi (jika belum selesai)
      const btnTandai = page.getByRole('button', { name: /Tandai Selesai/i });
      if (await btnTandai.isVisible()) {
        await btnTandai.click();
        await page.waitForTimeout(1500);
        console.log(`   ✅ Action 'Tandai Selesai' berhasil diklik.`);
      } else {
        console.log(`   ℹ️ Status materi sudah 'Selesai'.`);
      }

      // Klik 'Mulai Latihan →'
      const btnLatihan = page.getByRole('button', { name: /Mulai Latihan|Latihan/i });
      await expect(btnLatihan).toBeVisible();

      // Intersep API mulai kuis latihan
      const quizPromise = page.waitForResponse(
        (res) =>
          res.url().includes('/api/quiz/') &&
          res.url().endsWith('/mulai') &&
          res.request().method() === 'POST',
        { timeout: 15000 }
      );

      await btnLatihan.click();
      const quizResponse = await quizPromise;
      const quizPacket = await quizResponse.json();
      const quizData = quizPacket?.data ?? quizPacket;
      const quizQuestions = (quizData?.soal ?? quizData?.questions ?? []).map(
        (s: any) => s.soal ?? s
      );

      console.log(`   Ditemukan ${quizQuestions.length} butir soal latihan materi.`);

      // Jawab seluruh butir soal materi dengan akurasi kunci jawaban
      await solveExam(page, quizQuestions, `Latihan ${topic.name}`);

      await page.screenshot({ path: `test-results/materi-${t + 1}-selesai.png`, fullPage: true });
    }

    // ==========================================
    // 3. MENUJU SIMULASI SELEKSI
    // ==========================================
    console.log('\n--- 3. Verifikasi Syarat & Menjalankan Simulasi Seleksi ---');
    // Navigasi melalui klik sidebar agar router me-load data tingkat dan syarat dengan sempurna
    await page.getByRole('link', { name: 'Simulasi Seleksi' }).click();
    await page.waitForTimeout(4000);

    // Klik tombol 'Pilih Tingkat' Kabupaten/Kota jika ada
    const btnPilihTingkat = page.getByRole('button', { name: 'Pilih Tingkat' });
    if (await btnPilihTingkat.isVisible()) {
      await btnPilihTingkat.click();
      await page.waitForTimeout(1500);
    }

    // Verifikasi tombol 'Mulai Simulasi →' atau 'Lanjutkan Simulasi' sekarang aktif / enabled
    const btnMulaiSimulasi = page.getByRole('button', { name: /Mulai Simulasi|Lanjutkan/i }).first();
    await expect(btnMulaiSimulasi).toBeEnabled({ timeout: 20000 });
    console.log('🎉 Syarat simulasi TERPENUHI! Tombol Mulai/Lanjutkan Simulasi telah aktif.');

    // Intersep panggilan API simulasi jika baru mulai
    const simulasiPromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/simulasi/') &&
        (res.url().endsWith('/mulai') || res.url().includes('/soal') || res.url().endsWith('/ambil')) &&
        (res.request().method() === 'POST' || res.request().method() === 'GET'),
      { timeout: 20000 }
    ).catch(() => null);

    // Klik tombol Mulai/Lanjutkan Simulasi
    await btnMulaiSimulasi.click();
    await page.waitForTimeout(1000);

    // Centang persetujuan jika dialog konfirmasi muncul
    const dialogCheckbox = page.getByRole('dialog').getByRole('checkbox');
    if (await dialogCheckbox.isVisible()) {
      await dialogCheckbox.check();
      await page.waitForTimeout(500);

      const btnKonfirmasiMulai = page.getByRole('button', { name: /Mulai Simulasi/i }).last();
      await btnKonfirmasiMulai.click();
    }

    const simulasiResponse = await simulasiPromise;
    let simulasiQuestions: any[] = [];
    if (simulasiResponse) {
      try {
        const simulasiPacket = await simulasiResponse.json();
        const simulasiData = simulasiPacket?.data ?? simulasiPacket;
        simulasiQuestions = (simulasiData?.soal ?? simulasiData?.questions ?? []).map(
          (s: any) => s.soal ?? s
        );
      } catch {}
    }

    // Jika response API tidak terintersep (misal refresh/lanjutkan), ambil dari jumlah tombol nomor soal
    await page.locator('button.nomer').first().waitFor({ state: 'visible', timeout: 20000 });
    const countOnPage = await page.locator('button.nomer').count();

    if (simulasiQuestions.length === 0) {
      simulasiQuestions = Array.from({ length: countOnPage }, (_, i) => ({
        urutan: i + 1,
        tipe_soal: 'pilihan_ganda'
      }));
    }

    console.log(`✅ Simulasi aktif! Ditemukan ${simulasiQuestions.length} butir soal simulasi.`);

    // Jawab seluruh butir soal simulasi menggunakan kunci jawaban
    await solveExam(page, simulasiQuestions, 'Simulasi Kabupaten/Kota');

    // Pastikan diarahkan ke halaman hasil simulasi
    await expect(page).toHaveURL(/.*siswa\/simulasi\/hasil/, { timeout: 30000 });
    console.log('🏆 Seluruh pengerjaan simulasi telah berhasil diselesaikan dan dinilai!');

    // Screenshot hasil simulasi
    await page.screenshot({ path: 'test-results/simulasi-selesai-final.png', fullPage: true });
  });
});
