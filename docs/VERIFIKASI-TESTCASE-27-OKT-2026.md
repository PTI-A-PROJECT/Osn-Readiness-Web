# Verifikasi laporan testcase terhadap kode terbaru

Tanggal verifikasi: 27 Oktober 2026  
Kode acuan: branch `monorepo` pada commit `2643e4d`, setelah merge PR #35 dan #36.  
Sumber laporan: `TestCase/playwright-testing-siaposn/REPORT_TESTING_SIAP_OSN.md`.

Verifikasi ini membandingkan laporan dan skrip Playwright dengan source code terbaru. Hasil di deployment `https://mrcln0305.xyz` belum dianggap mewakili source terbaru sampai deployment commit-nya dibuktikan sama. Test Playwright dalam folder tersebut belum dijalankan ulang karena seluruh URL dan akun uji mengarah langsung ke production serta membuat/mengubah data nyata.

## Status temuan

| Temuan | Status pada kode terbaru | Bukti |
| --- | --- | --- |
| Tombol **Kumpulkan yang Kosong** disabled | **Masih terjadi** | `PengerjaanSoal.vue` men-disable tombol bila `bolehKumpulkanKosong=false`. Ketiga pemanggil (`PretestView`, `LatihanView`, `UjianSimulasiView`) tidak mengirim prop tersebut, sehingga nilai default selalu `false`, padahal handler masing-masing mendukung `{ paksa: true }`. Teks modal juga menawarkan pengumpulan kosong. |
| Direct URL/refresh `/siswa/simulasi` tidak memuat tingkat | **Tidak cocok dengan source terbaru** | `SimulasiView.vue` sudah menjalankan `pretest.muatTingkat({ force: true })` dalam `onMounted`, memilih tingkat default, lalu memanggil `simulasi.muatRuang()`. Kode ini sudah ada sebelum laporan dibuat. Jika bug masih muncul di production, kemungkinan deployment tertinggal, request API gagal, atau pesan kosong tampil sesaat saat loading. |
| Registrasi tidak menampilkan teks error password | **Sudah tidak terjadi di source** | `RegisterForm.vue` merender `passwordError` pada `<small id="password-error">`, beserta error nama, email, konfirmasi, dan referral. Test lama mencari selector saudara langsung `#password ~ span/p`, padahal input berada di dalam wrapper `<div>` dan pesan berada di luar wrapper; selector test menghasilkan false negative. |
| Input login/register tidak punya atribut `name` | **Masih terjadi** | Input sudah memiliki `id` dan `autocomplete`, tetapi belum memiliki `name`. Sesudah PR #35, field referral baru juga tidak memiliki `name`. Dampaknya terutama autofill, password manager, dan semantik form. |

Jadi, dari empat bug yang benar-benar ada di skrip: **dua masih terbuka, satu sudah selesai di source, dan satu tidak dapat direproduksi dari pembacaan source terbaru**.

## Ketidaksesuaian laporan dan testcase

1. Laporan menyatakan total **3 bug**, sementara `tests/bug-detection.spec.ts` berisi **4 test bug**. Temuan tombol Kumpulkan yang Kosong tidak masuk bagian analisis laporan.
2. Daftar isi menamai temuan sebagai BUG-02 sampai BUG-04, bagian detail menamainya BUG-01 sampai BUG-03, sedangkan skrip memakai BUG-01 sampai BUG-04.
3. Test direct-navigation hanya mencetak warning saat menemukan bug dan tidak memiliki assertion bahwa pesan harus muncul atau tidak muncul. Test tersebut dapat lulus baik ketika bug ada maupun tidak ada.
4. Test validasi password memakai selector yang tidak sesuai struktur DOM, sehingga menyimpulkan pesan tidak ada meskipun source merender pesan.
5. Test bug sengaja mengharapkan kondisi rusak (`disabled=true`, pesan error tidak ada). Setelah bug diperbaiki, suite akan merah. Jadikan test regresi yang mengharapkan perilaku benar.
6. Report menyatakan 10 skenario fungsional, tetapi tabel mencantumkan TC-01 sampai TC-13.
7. Report dan test menyimpan email serta kata sandi akun production secara langsung. Pindahkan ke environment variables dan rotasi kredensial yang sudah ter-commit bila akun masih dipakai.

## Dampak PR #35: testcase registrasi sudah usang

PR #35 mengubah alur sukses menjadi `Register -> auto-login -> /siswa`. Test berikut masih mengharapkan `/login` dan akan gagal pada source/deployment terbaru:

- `tests/register.spec.ts` TC01 dan TC02;
- `tests/end-to-end-flow.spec.ts` tahap registrasi, yang kemudian mencoba login manual;
- narasi dan diagram dalam laporan yang masih menempatkan login setelah registrasi.

Test perlu mengharapkan `/siswa`, memastikan token tersimpan dan dashboard tampil, lalu melanjutkan alur tanpa login kedua. Tambahkan test kode referral valid, tidak valid, kosong, normalisasi lowercase/spasi, kode pribadi pengguna baru, tombol salin, dan jumlah undangan.

## Dampak PR #36: cakupan testcase belum memadai

PR #36 mengurutkan materi wajib dari persentase terendah. Suite Playwright belum menguji perbedaan antara materi yang benar-benar mendapat 0% dan materi yang tidak mendapat soal. Kode terbaru memberi keduanya nilai `0.0`, sehingga materi yang tidak diuji dapat menjadi prioritas pertama. Tambahkan test kontrak yang memastikan kebijakan produk untuk materi tanpa soal; temuan ini tetap menjadi blocker logika sampai kebijakan ditetapkan.

## Task perbaikan

### Frontend

- **FE-QA-01 (P1):** aktifkan `boleh-kumpulkan-kosong` pada jenis pengerjaan yang memang mengizinkan submit parsial, atau ubah teks/modal bila submit parsial dilarang. Tambahkan component test untuk kedua kebijakan.
- **FE-QA-02 (P2):** tambahkan `name` semantik pada semua input login/register, termasuk checkbox dan referral.
- **FE-QA-03 (P1):** perbarui alur Playwright registrasi untuk auto-login dan dashboard.
- **FE-QA-04 (P2):** tambahkan state loading pada daftar tingkat agar pesan “Belum ada data tingkat” tidak tampil selama request masih berlangsung; bedakan loading, kosong, dan gagal.

### QA

- **QA-01 (P1):** ubah empat bug test menjadi regression test yang mengharapkan perilaku benar dan memiliki assertion wajib.
- **QA-02 (P1):** gunakan `baseURL`, `QA_EMAIL`, dan `QA_PASSWORD` dari environment; hentikan hardcode production URL/kredensial.
- **QA-03 (P1):** buat data test unik dan cleanup, lalu jalankan test stateful secara serial. Konfigurasi saat ini `fullyParallel: true` berisiko membuat test production saling memengaruhi.
- **QA-04 (P2):** perbarui jumlah skenario, penomoran bug, diagram auto-login, dan bukti hasil run pada report.
- **QA-05 (P2):** pisahkan suite production smoke yang read-only dari suite E2E yang membuat akun, mengerjakan ujian, dan mengubah progres.

### Backend dan Data Analytics

- **BE-QA-01 (P1):** perbaiki collision handling kode referral dan pemeriksaan kode milik soft-deleted user sesuai review PR #35.
- **DA-QA-01 (P1):** tetapkan perilaku materi tanpa soal pada rekomendasi dan uji berbeda dari materi dengan soal tetapi 0 jawaban benar.

## Urutan validasi ulang

1. Perbaiki testcase agar menguji perilaku yang diinginkan dan tidak memakai kredensial hardcoded.
2. Jalankan unit/build FE serta suite backend/analytics.
3. Jalankan E2E pada environment staging dengan database khusus.
4. Catat commit deployment, URL, waktu, jumlah test, dan artefak pada report.
5. Jalankan smoke test read-only pada production setelah deployment.
