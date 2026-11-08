# 🐞 LAPORAN TEMUAN BUG & ANALISIS DEFECT
## QA Automation Test Report & Defect Analysis Suite
### Aplikasi Web SIAP OSN — https://mrcln0305.xyz/
**Akun Pengujian:** `testqa6@gmail.com` | **Kata Sandi:** `test_12345678`  
**Framework:** Playwright Test v1.64.1 (Chromium Engine)  
**Waktu Pengujian:** November 2026  

---

## 📑 DAFTAR ISI
1. [Ringkasan Eksekutif (Executive Summary)](#1-ringkasan-eksekutif-executive-summary)
2. [Informasi Lingkungan & Kredensial Pengujian](#2-informasi-lingkungan--kredensial-pengujian)
3. [Tabel Rekapitulasi Temuan Bug (Defect Matrix)](#3-tabel-rekapitulasi-temuan-bug-defect-matrix)
4. [Laporan Analisis Detail Setiap Bug (Detailed Defect Reports)](#4-laporan-analisis-detail-setiap-bug)
   - [BUG-01: Information Disclosure Kebocoran Model Eloquent Laravel](#bug-01-information-disclosure-kebocoran-model-eloquent-laravel-pada-invalid-id)
   - [BUG-02: Fitur Ubah Kata Sandi Belum Aktif di Sisi Server](#bug-02-fitur-ubah-kata-sandi-menampilkan-pesan-penyimpanan-ke-server-belum-aktif)
   - [BUG-03: Input Asal Sekolah & Kelas Diabaikan saat Simpan Profil (Data Loss)](#bug-03-input-asal-sekolah-dan-kelas-diabaikan-saat-simpan-profil-data-loss)
   - [BUG-04: Tombol Ganti Foto Profil Tidak Berfungsi (Dead Button)](#bug-04-tombol-ganti-foto-profil-tidak-berfungsi-dead-button)
   - [BUG-05: Tombol Kumpulkan yang Kosong Hanya Menutup Modal Tanpa Submit](#bug-05-tombol-kumpulkan-yang-kosong-hanya-menutup-modal-tanpa-submit-ke-server)
   - [BUG-06: Form Registrasi Tidak Memunculkan Pesan Teks Error Validasi](#bug-06-form-registrasi-tidak-memunculkan-pesan-teks-error-validasi)
   - [BUG-07: Unduh Laporan Pemetaan Tanpa Metadata Identitas Siswa](#bug-07-unduh-laporan-pemetaan-menghasilkan-csv-mentah-tanpa-identitas-siswa)
   - [BUG-08: Elemen Input Formulir Profil Tanpa Atribut Semantik Standar](#bug-08-elemen-input-formulir-profil-tanpa-atribut-semantik-standar)
5. [Skrip Pengujian Otomasi Playwright & Bukti Screenshot](#5-skrip-pengujian-otomasi-playwright--bukti-screenshot)
6. [Panduan Menjalankan Pengujian Bug](#6-panduan-menjalankan-pengujian-bug)
7. [Kesimpulan & Rekomendasi Prioritas Perbaikan](#7-kesimpulan--rekomendasi-prioritas-perbaikan)

---

## 1. Ringkasan Eksekutif (Executive Summary)

Pengujian kualitas perangkat lunak (*Software Quality Assurance*) berbasis otomasi telah dilakukan secara intensif terhadap aplikasi web **SIAP OSN** yang beralamat pada domain publik **[https://mrcln0305.xyz/](https://mrcln0305.xyz/)** menggunakan akun siswa **`testqa6@gmail.com`**.

Dari serangkaian skenario pengujian fungsionalitas, keamanan (security checks), integritas data (data integrity), validasi formulir (form validation), serta pengalaman pengguna (usability/accessibility), berhasil diidentifikasi dan diverifikasi sebanyak **8 (delapan) temuan bug (defects)**.

```
+---------------------------------------------------------------------------------------+
| RINGKASAN METRIK TEMUAN BUG                                                           |
| Total Bug Terverifikasi : 8 Bug                                                       |
| • Severity HIGH         : 4 Bug (Information Disclosure, Incomplete Feature, Data Loss)|
| • Severity MEDIUM       : 2 Bug (Dead Button, Missing Visual Error Feedback)          |
| • Severity LOW          : 2 Bug (Missing Identity Metadata, Missing HTML Attributes)  |
| Status Verifikasi Otomasi: 100% Passed (Seluruh 8 bug terverifikasi oleh Playwright)  |
+---------------------------------------------------------------------------------------+
```

---

## 2. Informasi Lingkungan & Kredensial Pengujian

| Parameter | Spesifikasi |
| :--- | :--- |
| **URL Aplikasi Sasaran** | [https://mrcln0305.xyz/](https://mrcln0305.xyz/) |
| **URL Backend API** | [https://api.mrcln0305.xyz/api](https://api.mrcln0305.xyz/api) |
| **Frontend Stack** | Vue 3 SPA + Vite + Tailwind CSS |
| **Backend Stack** | PHP Laravel (Eloquent ORM, Laravel Sanctum) |
| **Akun Siswa (Pengujian)** | `testqa6@gmail.com` |
| **Kata Sandi Akun** | `test_12345678` |
| **Automation Tool** | [Playwright](https://playwright.dev/) v1.64.1 |
| **Browser Runner** | Chromium Headless & Headed |
| **Sistem Operasi** | Windows 11 (64-bit) |

---

## 3. Tabel Rekapitulasi Temuan Bug (Defect Matrix)

| ID Bug | Modul / Halaman | Deskripsi Bug | Kategori | Tingkat Keparahan (Severity) | Status Otomasi |
| :---: | :--- | :--- | :--- | :---: | :---: |
| **BUG-01** | `/siswa/latihan/:id`<br>`/siswa/simulasi/ujian/:id`<br>`/siswa/simulasi/hasil/:id` | Information Disclosure: kebocoran nama internal Model Eloquent Laravel saat mengakses ID tidak valid | Keamanan (CWE-209) | 🔴 **HIGH** | **VERIFIED (PASS)** |
| **BUG-02** | `/siswa/profil` | Fitur "Ubah Kata Sandi" belum terhubung ke API backend (*Penyimpanan ke server belum aktif*) | Fungsionalitas | 🔴 **HIGH** | **VERIFIED (PASS)** |
| **BUG-03** | `/siswa/profil` | Input "Asal Sekolah" dan "Kelas" diabaikan dari payload update profil (kehilangan data permanen) | Integritas Data | 🔴 **HIGH** | **VERIFIED (PASS)** |
| **BUG-04** | `/siswa/profil` | Tombol "Ganti foto profil" mati (*Dead Button*), tidak membuka file picker atau modal unggah | Interaksi / UX | 🟡 **MEDIUM** | **VERIFIED (PASS)** |
| **BUG-05** | `/siswa/simulasi/ujian/:id` | Tombol "Kumpulkan yang Kosong" pada modal konfirmasi ujian hanya menutup modal tanpa mengirim jawaban | Fungsionalitas Kritis | 🔴 **HIGH** | **VERIFIED (PASS)** |
| **BUG-06** | `/register` | Form pendaftaran tidak menampilkan pesan teks error saat validasi gagal, hanya mengubah warna border | Aksesibilitas & UX | 🟡 **MEDIUM** | **VERIFIED (PASS)** |
| **BUG-07** | `/siswa/pemetaan` | Berkas CSV unduhan pemetaan kompetensi tidak menyertakan nama siswa, email, atau tanggal cetak | Format Pelaporan | 🟢 **LOW** | **VERIFIED (PASS)** |
| **BUG-08** | `/siswa/profil` | Seluruh input formulir profil dan kode referal memiliki atribut `name=null` atau tanpa `id` | Standar HTML / Aksesibilitas | 🟢 **LOW** | **VERIFIED (PASS)** |

---

## 4. Laporan Analisis Detail Setiap Bug

### BUG-01: Information Disclosure Kebocoran Model Eloquent Laravel pada Invalid ID
- **ID Bug:** `BUG-01`
- **Tingkat Keparahan (Severity):** 🔴 **HIGH** (*Security Risk / CWE-209: Generation of Error Message Containing Sensitive Information*)
- **Komponen Terdampak:**
  - `GET /siswa/latihan/{id}` (contoh ID: `9999`)
  - `GET /siswa/simulasi/ujian/{id}` (contoh ID: `9999`)
  - `GET /siswa/simulasi/hasil/{id}` (contoh ID: `9999`)
  - `GET /siswa/simulasi/hasil/{id}/pembahasan` (contoh ID: `9999`)
  - Backend API: `GET /api/materi/{id}`, `GET /api/pretest/{id}`, `GET /api/hasil-simulasi/{id}`
- **Langkah Reproduksi:**
  1. Login menggunakan akun `testqa6@gmail.com`.
  2. Buka URL langsung dengan ID tidak terdaftar: `https://mrcln0305.xyz/siswa/latihan/9999` atau `https://mrcln0305.xyz/siswa/simulasi/ujian/9999`.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Aplikasi menampilkan halaman error ramah pengguna (404 Not Found kustom) bertuliskan *"Data latihan atau simulasi tidak ditemukan"*, tanpa membocorkan struktur kode backend.
- **Perilaku Aktual (Actual Behavior):**
  Aplikasi merender pesan error exception internal dari Laravel:
  > `"No query results for model [App\Models\Quiz] 9999"`  
  > `"No query results for model [App\Models\Simulasi] 9999"`  
  > `"No query results for model [App\Models\HasilSimulasi] 9999"`  
  Pesan ini mengekspos arsitektur namespace model internal Laravel, nama class model, dan konfigurasi database.
- **Bukti Screenshot:**
  - `results/screenshots/bug-01-leak-latihan.png`
  - `results/screenshots/bug-01-leak-simulasi.png`
  - `results/screenshots/bug-01-leak-hasil.png`
  - `results/screenshots/bug-01-leak-pembahasan.png`
- **Rekomendasi Perbaikan:**
  Pada backend Laravel (`app/Exceptions/Handler.php` atau `bootstrap/app.php`), tangani exception `ModelNotFoundException` agar mengembalikan respon JSON standar:
  ```php
  use Illuminate\Database\Eloquent\ModelNotFoundException;

  public function register()
  {
      $this->renderable(function (ModelNotFoundException $e, $request) {
          if ($request->wantsJson() || $request->is('api/*')) {
              return response()->json([
                  'status' => 'error',
                  'message' => 'Data yang diminta tidak ditemukan.'
              ], 404);
          }
      });
  }
  ```

---

### BUG-02: Fitur "Ubah Kata Sandi" Menampilkan Pesan "Penyimpanan ke server belum aktif"
- **ID Bug:** `BUG-02`
- **Tingkat Keparahan (Severity):** 🔴 **HIGH** (*Broken Feature / Security Management*)
- **Komponen Terdampak:** Halaman Profil Siswa (`/siswa/profil` bagian `02 · KEAMANAN`)
- **Langkah Reproduksi:**
  1. Buka menu profil siswa di `https://mrcln0305.xyz/siswa/profil`.
  2. Klik tombol **Ubah Kata Sandi**.
  3. Masukkan kata sandi saat ini (`test_12345678`), kata sandi baru (`test_12345678_new`), dan konfirmasi sandi.
  4. Klik tombol **Simpan**.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Sistem mengirimkan HTTP POST/PUT ke endpoint backend (misalnya `/api/auth/password` atau `/api/auth/change-password`) untuk memvalidasi sandi lama dan memperbarui sandi baru.
- **Perilaku Aktual (Actual Behavior):**
  Tidak ada request HTTP network yang dikirim ke server. Tampil pesan teks kuning/peringatan pada UI:
  > *"Kata sandi baru memenuhi syarat. Penyimpanan ke server belum aktif."*  
  Hasil audit API mengonfirmasi bahwa route `/api/auth/change-password` atau `/api/auth/password` berstatus **404 Route Not Found**. Pengguna tidak memiliki cara untuk mengamankan atau memperbarui kata sandi akun mereka.
- **Bukti Screenshot:**
  - `results/screenshots/bug-02-password-server-inactive.png`
- **Rekomendasi Perbaikan:**
  1. Implementasikan endpoint backend di Laravel:
     ```php
     Route::middleware('auth:sanctum')->post('/auth/change-password', [AuthController::class, 'changePassword']);
     ```
  2. Hubungkan handler tombol **Simpan** di frontend Vue agar memanggil API tersebut dan menampilkan pesan sukses.

---

### BUG-03: Input "Asal Sekolah" dan "Kelas" Diabaikan saat Simpan Profil (Data Loss)
- **ID Bug:** `BUG-03`
- **Tingkat Keparahan (Severity):** 🔴 **HIGH** (*Data Loss / Data Inconsistency*)
- **Komponen Terdampak:** Formulir Ubah Profil (`/siswa/profil`)
- **Langkah Reproduksi:**
  1. Buka halaman `https://mrcln0305.xyz/siswa/profil`.
  2. Klik tombol **Ubah Profil**.
  3. Isi input `Asal Sekolah` (misal: `"SMA Negeri 1 Malang"`) dan input `Kelas` (misal: `"11"`).
  4. Klik tombol **Simpan Perubahan**.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Payload permintaan `PUT /api/auth/profile` memuat atribut lengkap:
  `{ "name": "testqa6", "email": "testqa6@gmail.com", "sekolah": "SMA Negeri 1 Malang", "kelas": "11" }`.
- **Perilaku Aktual (Actual Behavior):**
  Berdasarkan inspeksi network Playwright, payload yang dikirimkan **hanya**:
  `{ "name": "testqa6", "email": "testqa6@gmail.com" }`.  
  Nilai input sekolah dan kelas dibuang (*dropped*) oleh frontend sebelum dikirim, dan field tersebut tetap kosong setelah reload. Hal ini menyebabkan hilangnya data profil siswa secara permanen.
- **Bukti Screenshot:**
  - `results/screenshots/bug-03-profile-data-loss.png`
- **Rekomendasi Perbaikan:**
  Di komponen `ProfilView.vue`, tambahkan binding `v-model` untuk sekolah dan kelas ke dalam state form payload:
  ```typescript
  const simpanProfil = async () => {
    await api.put('/auth/profile', {
      name: form.name,
      email: form.email,
      sekolah: form.sekolah,
      kelas: form.kelas
    });
  };
  ```

---

### BUG-04: Tombol "Ganti foto profil" Tidak Berfungsi (Dead Button)
- **ID Bug:** `BUG-04`
- **Tingkat Keparahan (Severity):** 🟡 **MEDIUM** (*Usability / Dead Interactive Element*)
- **Komponen Terdampak:** Kartu Identitas Siswa (`/siswa/profil`)
- **Langkah Reproduksi:**
  1. Buka halaman `https://mrcln0305.xyz/siswa/profil`.
  2. Klik tombol **Ganti foto profil** di samping avatar nama siswa.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Muncul dialog pemilihan berkas (*File Picker Dialog*) dari sistem operasi atau muncul modal untuk mengunggah avatar baru.
- **Perilaku Aktual (Actual Behavior):**
  Tombol dapat diklik tetapi tidak memicu aksi apapun (*dead button*). Tidak ada elemen `<input type="file">` tersembunyi, tidak ada modal dialog yang terbuka, dan tidak ada network request yang dibuat.
- **Bukti Screenshot:**
  - `results/screenshots/bug-04-dead-button-ganti-foto.png`
- **Rekomendasi Perbaikan:**
  Tambahkan input file tersembunyi yang ditrigger saat tombol diklik:
  ```html
  <input type="file" ref="fileInput" accept="image/*" class="hidden" @change="uploadFoto" />
  <button @click="$refs.fileInput.click()" class="btn">Ganti foto profil</button>
  ```

---

### BUG-05: Tombol "Kumpulkan yang Kosong" Hanya Menutup Modal Tanpa Submit ke Server
- **ID Bug:** `BUG-05`
- **Tingkat Keparahan (Severity):** 🔴 **HIGH** (*Critical Business Flow / Test Submission Failure*)
- **Komponen Terdampak:** Ruang Ujian Simulasi (`/siswa/simulasi/ujian/:id`)
- **Langkah Reproduksi:**
  1. Masuk ke halaman pengerjaan simulasi ujian `https://mrcln0305.xyz/siswa/simulasi/ujian/1`.
  2. Buka modal submit dengan mengeklik tombol **Selesai**.
  3. Pada dialog peringatan bahwa ada soal yang belum terjawab, klik tombol **Kumpulkan yang Kosong** (`button.mbtn-ghost`).
- **Perilaku yang Diharapkan (Expected Behavior):**
  Sistem mengirimkan request `POST /api/simulasi/selesai` atau `POST /api/simulasi/submit` ke server untuk memfinalisasi lembar jawaban, kemudian mengalihkan siswa ke halaman hasil penilaian.
- **Perilaku Aktual (Actual Behavior):**
  Tombol hanya menjalankan aksi `showModal = false` di sisi lokal Vue (hanya menutup modal dialog konfirmasi). Siswa tetap berada di URL `/siswa/simulasi/ujian/1` dan jawaban tidak pernah terkirim ke server.
- **Bukti Screenshot:**
  - `results/screenshots/bug-05-unhandled-kumpulkan-kosong.png`
- **Rekomendasi Perbaikan:**
  Hubungkan event click tombol `Kumpulkan yang Kosong` ke method eksekusi submit ujian:
  ```html
  <button class="mbtn-ghost" @click="konfirmasiSubmitUjian(true)">
    Kumpulkan yang Kosong
  </button>
  ```

---

### BUG-06: Form Registrasi Tidak Memunculkan Pesan Teks Error Validasi
- **ID Bug:** `BUG-06`
- **Tingkat Keparahan (Severity):** 🟡 **MEDIUM** (*Form Validation / User Guidance*)
- **Komponen Terdampak:** Formulir Registrasi (`https://mrcln0305.xyz/register`)
- **Langkah Reproduksi:**
  1. Buka halaman pendaftaran `https://mrcln0305.xyz/register`.
  2. Masukkan data dengan kata sandi kurang dari 8 karakter (misalnya `"123"`).
  3. Klik tombol **Daftar Sekarang**.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Muncul teks deskripsi error di bawah input kata sandi (misalnya: *"Kata sandi minimal 8 karakter dan kombinasi huruf/angka"*).
- **Perilaku Aktual (Actual Behavior):**
  Input kata sandi hanya diberi atribut HTML `aria-invalid="true"` dan border merah, namun **sama sekali tidak ada teks keterangan error** yang menjelaskan mengapa pendaftaran gagal. Pengguna awam tidak mendapatkan petunjuk perbaikan.
- **Bukti Screenshot:**
  - `results/screenshots/bug-06-register-missing-error-text.png`
- **Rekomendasi Perbaikan:**
  Tambahkan komponen pesan error visual:
  ```html
  <span v-if="errors.password" class="text-xs text-red-500 mt-1 block">
    {{ errors.password }}
  </span>
  ```

---

### BUG-07: Unduh Laporan Pemetaan Menghasilkan CSV Mentah Tanpa Identitas Siswa
- **ID Bug:** `BUG-07`
- **Tingkat Keparahan (Severity):** 🟢 **LOW** (*Reporting / Audit Trail*)
- **Komponen Terdampak:** Menu Pemetaan Kompetensi (`/siswa/pemetaan`)
- **Langkah Reproduksi:**
  1. Buka menu pemetaan di `https://mrcln0305.xyz/siswa/pemetaan`.
  2. Klik tombol **Unduh laporan**.
  3. Buka berkas CSV yang terunduh.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Laporan CSV memuat metadata kepala (*header info*) seperti Nama Siswa, Alamat Email, Tanggal Unduh, dan Tingkat Seleksi sebelum baris data kompetensi.
- **Perilaku Aktual (Actual Behavior):**
  Berkas CSV hanya memuat data tabel mentah tanpa informasi kepemilikan siswa:
  ```csv
  ﻿"Materi","Persentase Penguasaan","Status"
  "Materi 5","0%","Belum dikuasai"
  "Materi 3","17%","Belum dikuasai"
  "Materi 4","38%","Belum dikuasai"
  "Materi 2","0%","Belum dikuasai"
  "Materi 1","8%","Belum dikuasai"
  ```
  Jika berkas ini diunduh dan dibagikan ke guru atau pelatih OSN, pemilik laporan tidak dapat diverifikasi.
- **Bukti Screenshot:**
  - `results/screenshots/bug-07-unduh-laporan.png`
- **Rekomendasi Perbaikan:**
  Sertakan baris metadata pada fungsi pembentuk CSV di frontend:
  ```javascript
  const headerMeta = `# Laporan Pemetaan Kompetensi SIAP OSN\n# Nama: ${user.name}\n# Email: ${user.email}\n# Tanggal: ${new Date().toLocaleDateString('id-ID')}\n\n`;
  ```

---

### BUG-08: Elemen Input Formulir Profil Tanpa Atribut Semantik Standar
- **ID Bug:** `BUG-08`
- **Tingkat Keparahan (Severity):** 🟢 **LOW** (*HTML5 Semantics / Accessibility / Password Managers*)
- **Komponen Terdampak:** Formulir Profil Siswa (`/siswa/profil`)
- **Langkah Reproduksi:**
  1. Buka halaman `https://mrcln0305.xyz/siswa/profil`.
  2. Lakukan inspeksi elemen DOM terhadap tag-tag `<input>`.
- **Perilaku yang Diharapkan (Expected Behavior):**
  Seluruh elemen formulir memiliki atribut `name` dan `id` yang unik dan standar sesuai kaidah HTML5.
- **Perilaku Aktual (Actual Behavior):**
  Berdasarkan evaluasi Playwright DOM:
  - Input `nama`, `email`, `sekolah`, `kelas`, `tingkat`, `bergabung` memiliki `name: null`.
  - Input kode referal memiliki `id: ""` (kosong) dan `name: null`.  
  Kondisi ini menyebabkan fitur autofill browser dan *password manager* (Google Password Manager, Bitwarden, 1Password) gagal mengenali field, serta merusak navigasi pembaca layar (*screen reader* / NVDA).
- **Bukti Screenshot:**
  - `results/screenshots/bug-08-missing-attributes.png`
- **Rekomendasi Perbaikan:**
  Tambahkan atribut `name` dan `id` lengkap pada seluruh field input di `ProfilView.vue`.

---

## 5. Skrip Pengujian Otomasi Playwright & Bukti Screenshot

Seluruh pengujian bug ini diotomasi menggunakan Playwright Test Suite yang tersimpan pada:
- **File Skrip:** `tests/bug-testing/bug-detection-testqa6.spec.ts`
- **File Konfigurasi:** `playwright.config.ts`
- **File Sesi Autentikasi:** `auth-testqa6.json`

### Daftar Berkas Bukti Tangkapan Layar (Screenshots):
Semua bukti tangkapan layar otomatis tersimpan di dalam folder `results/screenshots/`:
1. `results/screenshots/bug-01-leak-latihan.png`
2. `results/screenshots/bug-01-leak-simulasi.png`
3. `results/screenshots/bug-01-leak-hasil.png`
4. `results/screenshots/bug-01-leak-pembahasan.png`
5. `results/screenshots/bug-02-password-server-inactive.png`
6. `results/screenshots/bug-03-profile-data-loss.png`
7. `results/screenshots/bug-04-dead-button-ganti-foto.png`
8. `results/screenshots/bug-05-unhandled-kumpulkan-kosong.png`
9. `results/screenshots/bug-06-register-missing-error-text.png`
10. `results/screenshots/bug-07-unduh-laporan.png`
11. `results/screenshots/bug-08-missing-attributes.png`

---

## 6. Panduan Menjalankan Pengujian Bug

Untuk memverifikasi dan menjalankan ulang seluruh suite pengujian deteksi bug menggunakan akun `testqa6@gmail.com`:

```bash
# 1. Jalankan pengujian bug testqa6 (mode otomatis)
npm run test:bugs:qa6

# 2. Atau jalankan langsung via Playwright CLI
npx playwright test tests/bug-testing/bug-detection-testqa6.spec.ts --project=chromium

# 3. Menjalankan dengan tampilan browser interaktif (headed mode)
npx playwright test tests/bug-testing/bug-detection-testqa6.spec.ts --project=chromium --headed

# 4. Membuka laporan HTML interaktif Playwright
npx playwright show-report
```

---

## 7. Kesimpulan & Rekomendasi Prioritas Perbaikan

Berdasarkan temuan pengujian otomasi, sistem **SIAP OSN** telah memiliki alur bisnis dasar yang kaya, namun membutuhkan perbaikan teknis pada modul-modul penting sebelum siap produksi secara sempurna:

1. **Prioritas Utama (P1 - Security & Critical Functionality):**
   - Perbaiki **BUG-01** dengan menangani `ModelNotFoundException` di Laravel agar tidak membocorkan nama internal Model Eloquent.
   - Perbaiki **BUG-05** agar tombol "Kumpulkan yang Kosong" pada modal konfirmasi ujian benar-benar mengirimkan jawaban ke server.
   - Perbaiki **BUG-02** dengan membuat endpoint `/api/auth/change-password` agar fitur ganti kata sandi berfungsi.
   - Perbaiki **BUG-03** agar input "Asal Sekolah" dan "Kelas" disertakan dalam payload request dan tersimpan di database.
2. **Prioritas Menengah (P2 - Usability & UX):**
   - Perbaiki **BUG-04** dengan mengaktifkan file picker untuk unggah foto profil.
   - Perbaiki **BUG-06** dengan menampilkan teks pesan error validasi pada form registrasi.
3. **Prioritas Penyempurnaan (P3 - Polish & Standards):**
   - Perbaiki **BUG-07** dengan menyematkan header metadata siswa pada laporan CSV.
   - Perbaiki **BUG-08** dengan melengkapi atribut HTML5 semantik (`name` dan `id`) pada form profil.
