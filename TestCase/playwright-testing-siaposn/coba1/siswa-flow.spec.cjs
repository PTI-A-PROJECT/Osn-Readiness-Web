const {
  test,
  expect,
} = require("../../../apps/siap-osn-fe/node_modules/@playwright/test");
const fs = require("node:fs");
const path = require("node:path");

const PAUSE = 3500;
const angka = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
};
const keyRows = fs
  .readFileSync(path.join(__dirname, "..", "keys.psv"), "utf8")
  .trim()
  .split(/\r?\n/)
  .map((line) => {
    const [id, materiId, tipe, key, pilihan] = line.split("|");
    return {
      id: String(id),
      materiId: String(materiId),
      tipe,
      key,
      pilihan: JSON.parse(pilihan),
    };
  });
const keyByQuestion = new Map(keyRows.map((row) => [row.id, row]));
const email = `siswa.e2e.${Date.now()}@example.test`;
const password = "E2ePassword123";

async function pause(page, label) {
  console.log(`\n[E2E] ${label}`);
  await page.waitForTimeout(PAUSE);
}

async function answerCurrentExam(
  page,
  questions,
  { isCorrect = () => true } = {},
) {
  // UI selalu mengurutkan soal berdasarkan `urutan` (lihat paketPretest dan
  // paketSimulasi), jadi script harus memakai urutan yang sama supaya nomor
  // yang diklik benar-benar soal yang dimaksud.
  const soalUrut = [...questions].sort(
    (a, b) => angka(a.urutan) - angka(b.urutan),
  );
  for (let index = 0; index < soalUrut.length; index++) {
    const item = soalUrut[index];
    const keyRow = keyByQuestion.get(String(item.id));
    if (!keyRow)
      throw new Error(
        `Kunci soal ${item.id} tidak ditemukan di fixture database`,
      );
    const benar = isCorrect(index, item);
    // Klik nomor lalu tunggu UI benar-benar pindah. Playwright count()
    // maupun innerText() tidak menunggu elemen baru, jadi tanpa tunggu ini
    // script bisa membaca opsi/textarea milik soal sebelumnya.
    await page.locator(".nomer-grid .nomer").nth(index).click();
    await expect(page.locator(".soal-meta-left .chip").first()).toHaveText(
      new RegExp(`Soal ${index + 1} dari`),
    );
    if (keyRow.tipe === "pilihan_ganda") {
      const options = page.locator(".opsi-list .opsi");
      await expect(options.first()).toBeVisible();
      const optionCount = await options.count();
      let correctIndex = -1;
      for (let optionIndex = 0; optionIndex < optionCount; optionIndex++) {
        const label = (await options.nth(optionIndex).locator("b").innerText())
          .trim()
          .toLowerCase();
        if (label.startsWith(keyRow.key.trim().toLowerCase())) {
          correctIndex = optionIndex;
          break;
        }
      }
      if (correctIndex < 0)
        throw new Error(
          `Opsi benar ${keyRow.key} untuk soal ${item.id} tidak ditemukan`,
        );
      await options
        .nth(benar ? correctIndex : (correctIndex + 1) % optionCount)
        .click();
    } else {
      const textarea = page.locator("textarea.uraian");
      await expect(textarea).toBeVisible();
      await textarea.fill(benar ? keyRow.key : "__jawaban_sengaja_salah__");
    }
    // Autosave memakai debounce 800 ms plus satu request; jeda harus lebih
    // panjang dari itu supaya jawaban benar-benar terkirim sebelum pindah.
    await page.waitForTimeout(1600);
  }
  await page.getByRole("button", { name: /Selesai/i }).click();
  await page
    .getByRole("button", { name: /Kumpulkan|Ya|Submit/i })
    .last()
    .click();
}

test("alur siswa Kabupaten menguji urutan rekomendasi materi", async ({
  page,
}) => {
  test.setTimeout(0);

  await page.goto("/register");
  await pause(page, "Halaman registrasi terbuka");

  await page.locator("#nama").fill("Siswa E2E Kabupaten");
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await page.locator("#konfirmasi").fill(password);
  await page.locator("#agreement").check();
  await expect(page.locator("#agreement")).toBeChecked();
  await page.locator("form").last().locator('button[type="submit"]').click();
  await page.waitForTimeout(1800);
  await pause(page, "Registrasi akun otomatis selesai");

  await page.goto("/login", { waitUntil: "domcontentloaded", timeout: 30000 });
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await page
    .getByRole("button", { name: /Masuk ke Akun|Masuk/i })
    .first()
    .click();
  await page.waitForURL(/\/siswa(?:$|\/)/, { timeout: 30000 });
  await pause(page, "Login siswa berhasil dan dashboard terbuka");

  await page.goto("/siswa/pretest");
  await page.getByRole("button", { name: /Kabupaten/i }).click();
  await page.getByRole("button", { name: "Mulai Pre-Test" }).click();
  await page.locator(".soal").waitFor();
  await pause(page, "Pretest Kabupaten dimulai");

  const soal = await page.locator(".soal-meta").count();
  expect(soal).toBe(1);

  // Jawaban dikumpulkan dari response API halaman pretest melalui browser context.
  const responsePromise = page.waitForResponse(
    (response) =>
      response.url().includes("/api/pretest/") &&
      response.request().method() === "GET",
  );
  await page.reload();
  const response = await responsePromise;
  const pretestData = await response.json();
  const questions = (pretestData?.data?.soal ?? pretestData?.soal ?? []).map(
    (s) => s.soal ?? s,
  );
  expect(questions.length).toBeGreaterThan(0);

  const byMateri = new Map();
  for (const item of questions) {
    const materiId = String(item.materi_id ?? item.materiId ?? "unknown");
    const keyRow = keyByQuestion.get(String(item.id));
    if (!keyRow)
      throw new Error(
        `Kunci soal ${item.id} tidak ditemukan di fixture database`,
      );
    if (!byMateri.has(materiId)) byMateri.set(materiId, []);
    byMateri.get(materiId).push({ item, keyRow });
  }
  const targetMateri = [...byMateri.entries()]
    .sort((a, b) => b[1].length - a[1].length)
    .slice(0, 3)
    .map(([id, items], rank) => ({ id, items, rank }));
  expect(targetMateri.length).toBe(3);
  const targetById = new Map(targetMateri.map((target) => [target.id, target]));
  console.log(
    "[E2E] Target materi:",
    targetMateri.map((t) => `${t.id} (${t.items.length} soal)`).join(", "),
  );

  // Target 1 = 0% benar; target 2 = sekitar 30%; target 3 = sekitar 40%.
  // Materi lain dijawab benar. Semua pilihan diambil dari kunci database.
  const nomorDalamMateri = new Map();
  for (const target of targetMateri) {
    target.items.forEach((entry, urutan) =>
      nomorDalamMateri.set(String(entry.item.id), urutan),
    );
  }
  const isCorrectPretest = (_index, item) => {
    const target = targetById.get(
      String(item.materi_id ?? item.materiId ?? "unknown"),
    );
    if (!target) return true;
    const urutan = nomorDalamMateri.get(String(item.id));
    const ambang = target.rank === 1 ? 0.3 : target.rank === 2 ? 0.4 : 0;
    return urutan < Math.ceil(target.items.length * ambang);
  };
  await answerCurrentExam(page, questions, { isCorrect: isCorrectPretest });
  await pause(page, "Konfirmasi pengumpulan pretest");
  await expect(page).toHaveURL(/\/siswa\/pemetaan/, { timeout: 60000 });
  await pause(page, "Hasil pretest dan pemetaan terbuka");

  await page.goto("/siswa");
  await pause(page, "Dashboard siswa setelah pretest");
  const dashboardText = await page.locator("body").innerText();
  console.log(
    "[E2E] Dashboard contains recommendation terms:",
    dashboardText.includes("Direkomendasikan"),
  );

  await page.goto("/siswa/materi");
  await page.locator("text=Daftar Materi").waitFor();
  await pause(page, "Daftar materi rekomendasi terbuka");
  const materialButtons = page
    .locator("section")
    .filter({ hasText: "Daftar Materi" })
    .getByRole("button");
  expect(await materialButtons.count()).toBeGreaterThanOrEqual(3);

  for (let rank = 0; rank < 3; rank++) {
    await page.goto("/siswa/materi");
    await page.locator("text=Daftar Materi").waitFor();
    await page
      .locator("section")
      .filter({ hasText: "Daftar Materi" })
      .getByRole("button")
      .nth(rank)
      .click();
    await page
      .getByRole("button", { name: /Tandai Selesai/i })
      .click()
      .catch(() => {});
    await pause(page, `Materi prioritas ${rank + 1} selesai`);

    const latihanButton = page.getByRole("button", { name: /Mulai Latihan/i });
    await expect(latihanButton).toBeVisible();
    const quizResponse = page.waitForResponse(
      (response) =>
        response.url().includes("/api/quiz/") &&
        response.url().endsWith("/mulai") &&
        response.request().method() === "POST",
    );
    await latihanButton.click();
    const quizPacket = await (await quizResponse).json();
    await page.locator(".soal").waitFor();
    const quizData = quizPacket?.data ?? quizPacket;
    const quizQuestions = (quizData?.soal ?? quizData?.questions ?? []).map(
      (s) => s.soal ?? s,
    );
    expect(quizQuestions.length).toBeGreaterThan(0);
    console.log(
      `[E2E] Latihan prioritas ${rank + 1}: ${quizQuestions.length} soal`,
    );
    await answerCurrentExam(page, quizQuestions);
    await expect(page.getByText("Hasil Latihan")).toBeVisible({
      timeout: 60000,
    });
    console.log(`[E2E] Latihan prioritas ${rank + 1} selesai`);
  }

  await page.goto("/siswa/simulasi");
  await pause(page, "Syarat simulasi setelah semua latihan");
  const body = await page.locator("body").innerText();
  console.log("[E2E] Syarat simulasi:", body.slice(0, 1200));
  const levelCard = page
    .locator("article")
    .filter({ hasText: "Kabupaten/Kota" })
    .first();
  await levelCard.getByRole("button", { name: /Pilih Tingkat/i }).click();
  await expect(
    page.getByRole("button", { name: /Mulai Simulasi/i }),
  ).toBeEnabled();
  await page.getByRole("button", { name: /Mulai Simulasi/i }).click();
  await page.getByRole("dialog").getByRole("checkbox").check();
  const simulasiResponse = page.waitForResponse(
    (response) =>
      response.url().includes("/api/simulasi/") &&
      response.url().endsWith("/mulai") &&
      response.request().method() === "POST",
  );
  await page
    .getByRole("button", { name: /Mulai Simulasi/i })
    .last()
    .click();
  const simulasiPacket = await (await simulasiResponse).json();
  await page.locator(".soal").waitFor();
  const simulasiData = simulasiPacket?.data ?? simulasiPacket;
  const simulasiQuestions = (
    simulasiData?.soal ??
    simulasiData?.questions ??
    []
  ).map((s) => s.soal ?? s);
  expect(simulasiQuestions.length).toBeGreaterThan(0);
  console.log(`[E2E] Simulasi: ${simulasiQuestions.length} soal`);
  await answerCurrentExam(page, simulasiQuestions);
  await expect(page).toHaveURL(/\/siswa\/simulasi\/hasil\//, {
    timeout: 60000,
  });
  await pause(page, "Simulasi selesai dan hasil kenaikan tingkat terbuka");
  await page.screenshot({ path: "e2e-tmp/simulasi-final.png", fullPage: true });

  // ===== 2. SESI MASIH AKTIF — langsung buka profil dan ambil kode referal
  //      sebelum logout (kode hanya bisa dibaca oleh pemilik akun) =====
  await page.goto("/siswa/profil");
  const kodeInput = page.locator("input.referal-share__code");
  await expect(kodeInput).toBeVisible();
  await pause(page, "Profil terbuka — kode referal terlihat");

  const kodeReferal = (await kodeInput.inputValue()).trim();
  // Format: 4 karakter campuran huruf+angka uppercase, tanpa 0/O/1/I.
  expect(kodeReferal).toMatch(/^[2-9A-HJ-NP-Z]{4}$/);
  console.log(`[E2E] Kode referal akun pertama: ${kodeReferal}`);

  const hitunganAwal = angka(
    (await page.locator("p.referal-share__count").innerText()).match(
      /^(\d+)/,
    )?.[1] ?? 0,
  );
  await pause(
    page,
    `Kode referal ${kodeReferal} terbaca (jumlah undang awal: ${hitunganAwal})`,
  );

  // ===== 3. Baru logout via tombol "Keluar Sekarang" milik halaman profil
  //      (halaman profil tidak memakai UserMenu dropdown) =====
  await page.getByRole("button", { name: /Keluar Sekarang/i }).click();
  await page.waitForURL(/\/login/, { timeout: 30000 });
  await pause(page, "Logout via tombol profil — sampai di halaman login");

  // ===== 4. Daftar akun baru dengan kode referal dari langkah 2 =====
  const emailBaru = `referal.e2e.${Date.now()}@example.test`;
  await page.goto("/register");
  await pause(page, "Halaman registrasi akun kedua terbuka");
  await page.locator("#nama").fill("Siswa Undangan E2E");
  await page.locator("#email").fill(emailBaru);
  await page.locator("#password").fill(password);
  await page.locator("#konfirmasi").fill(password);
  await page.locator("#referal").fill(kodeReferal);
  await page.locator("#agreement").check();
  await expect(page.locator("#referal")).toHaveValue(kodeReferal);
  await pause(page, `Form terisi — kode referal: ${kodeReferal}`);
  await page.locator("form").last().locator('button[type="submit"]').click();
  // RegisterView mengalihkan ke /login setelah registrasi berhasil
  // (bukan auto-login) — jadi tunggu URL /login, bukan /siswa.
  await page.waitForURL(/\/login/, { timeout: 30000 });
  await pause(page, "Akun baru terdaftar — dialihkan ke halaman login");

  // Login akun baru supaya bisa membuka profil dan cek kode sendiri.
  await page.locator("#email").fill(emailBaru);
  await page.locator("#password").fill(password);
  await page
    .getByRole("button", { name: /Masuk ke Akun|Masuk/i })
    .first()
    .click();
  await page.waitForURL(/\/siswa(?:$|\/)/, { timeout: 30000 });
  await pause(page, "Login akun baru berhasil");

  // ===== 5. Kode akun baru harus unik dan BUKAN salinan dari pengundang =====
  await page.goto("/siswa/profil");
  await page.locator("input.referal-share__code").waitFor();
  const kodeBaru = (
    await page.locator("input.referal-share__code").inputValue()
  ).trim();
  expect(kodeBaru).toMatch(/^[2-9A-HJ-NP-Z]{4}$/);
  expect(kodeBaru).not.toBe(kodeReferal);
  await pause(
    page,
    `Profil akun baru: kode sendiri ${kodeBaru} (bukan salinan ${kodeReferal})`,
  );

  // ===== 6. Logout akun baru (masih di profil — pakai tombol profil) =====
  await page.getByRole("button", { name: /Keluar Sekarang/i }).click();
  await page.waitForURL(/\/login/, { timeout: 30000 });
  await pause(page, "Logout akun baru");

  // ===== 7. Login akun pertama — hitungan harus naik 1 =====
  await page.goto("/login", { waitUntil: "domcontentloaded", timeout: 30000 });
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await page
    .getByRole("button", { name: /Masuk ke Akun|Masuk/i })
    .first()
    .click();
  await page.waitForURL(/\/siswa(?:$|\/)/, { timeout: 30000 });
  await page.goto("/siswa/profil");
  const hitunganBaru = angka(
    (await page.locator("p.referal-share__count").innerText()).match(
      /^(\d+)/,
    )?.[1] ?? 0,
  );
  expect(hitunganBaru).toBe(hitunganAwal + 1);
  console.log(`[E2E] Jumlah undang naik: ${hitunganAwal} -> ${hitunganBaru}`);
  await pause(
    page,
    `Profil pengundang: ${hitunganBaru} pengguna sudah mendaftar lewat kode Anda`,
  );
});
