# 📖 Panduan Menjalankan Pengujian Otomasi Playwright SIAP OSN
## Alur Penuh Siswa Baru (Akun: `testqa6@gmail.com`)

Dokumen ini berisi panduan lengkap untuk menjalankan skrip pengujian otomasi end-to-end yang menguji seluruh fitur baru pada website **[https://mrcln0305.xyz/](https://mrcln0305.xyz/)**.

---

## 📌 Ringkasan Alur yang Diuji
1. **Registrasi Akun Baru**: Mendaftarkan akun `testqa6@gmail.com` (atau auto-login jika sudah terdaftar).
2. **Pre-Test pada Menu Pemetaan Kompetensi**: Mengerjakan 30 butir soal pre-test awal untuk mendapatkan hasil pemetaan kompetensi.
3. **Membaca Seluruh Materi**: Masuk ke menu **Materi**, membuka setiap modul materi, dan menandai seluruh submateri sebagai selesai (**✓ Tandai sebagai Selesai**).
4. **Pengerjaan Seluruh Latihan Materi**: Mengerjakan 10 butir soal latihan untuk setiap modul materi dengan kunci jawaban otomatis berakurasi tinggi.
5. **Pengecekan Menu Riwayat Hasil**: Membuka menu **Riwayat Hasil** dan memverifikasi histori pengerjaan pre-test dan latihan tersimpan rapi.
6. **Simulasi Seleksi OSN Tingkat Kabupaten/Kota**: Memastikan syarat simulasi terpenuhi, memulai simulasi, mengerjakan 30 butir soal simulasi dengan kunci jawaban, dan melihat hasil penilaian kelulusan.

---

## 🚀 Cara Menjalankan Pengujian

### 1. Menjalankan dengan Tampilan Browser Chrome (Mode Headed)
Untuk melihat browser Chrome secara langsung saat script berjalan dan berinteraksi otomatis dengan website:

```bash
npx playwright test tests/functional/full-flow-new-account.spec.ts --project=chromium --headed
```

### 2. Menjalankan di Latar Belakang (Mode Headless)
Untuk eksekusi lebih cepat tanpa membuka jendela browser:

```bash
npx playwright test tests/functional/full-flow-new-account.spec.ts --project=chromium
```

### 3. Menjalankan Suite Deteksi Bug
Untuk menjalankan skrip pengujian deteksi bug:

```bash
npm run test:bugs:qa6
```

### 4. Menjalankan Mode UI Interaktif Playwright
Untuk menjalankan pengujian dengan antarmuka debugger visual Playwright:

```bash
npm run test:ui
```

---

## 📷 Lokasi Bukti Tangkapan Layar (Screenshots)
Setiap tahapan pengujian secara otomatis mengambil tangkapan layar dan menyimpannya di folder `results/screenshots/`:
- `test-results/01-dashboard-siswa.png`: Halaman awal Dashboard Siswa setelah registrasi/login.
- `test-results/02-hasil-pemetaan-kompetensi.png`: Halaman hasil evaluasi dan pemetaan kompetensi setelah pre-test.
- `test-results/03-materi-1-selesai.png` s.d. `03-materi-5-selesai.png`: Bukti penyelesaian materi & latihan untuk ke-5 modul pembelajaran.
- `test-results/04-riwayat-hasil.png`: Rekapitulasi histori pengerjaan dan skor pada menu Riwayat Hasil.
- `test-results/05-simulasi-kabupaten-selesai.png`: Halaman hasil dan perolehan skor Simulasi Seleksi OSN Tingkat Kabupaten/Kota.

---

## 📋 Catatan Teknis & Penanganan Error yang Telah Diperbaiki
1. **Pemisahan State Browser**: Seluruh alur (Registrasi -> Pre-test -> Materi -> Latihan -> Riwayat -> Simulasi) digabungkan dalam satu sesi browser berurutan agar status autentikasi/login siswa tidak terputus di tengah jalan.
2. **Kesesuaian Selector Kartu Materi**: Pemilihan modul pembelajaran kini menggunakan selector `article.material-card` yang spesifik dan tahan terhadap perubahan DOM dinamis.
3. **Penyelesaian Bertahap Submateri**: Pengujian secara adaptif mendeteksi seluruh tombol `Tandai sebagai Selesai` hingga seluruh submateri terselesaikan 100%.
4. **Pemenuhan Syarat Simulasi**: Semua modul materi wajib diselesaikan dengan nilai $\ge 50$ menggunakan kunci jawaban sehingga tombol `Mulai Simulasi` otomatis aktif tanpa hambatan.
