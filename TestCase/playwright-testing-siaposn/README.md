# 🧪 SIAP OSN QA Automation & Bug Testing Suite

Proyek pengujian otomasi dan deteksi bug untuk platform pembelajaran dan simulasi seleksi **SIAP OSN** ([https://mrcln0305.xyz/](https://mrcln0305.xyz/)) menggunakan [Playwright Test](https://playwright.dev/).

---

## 📂 Struktur Direktori Proyek

Proyek ini telah dikelompokkan ke dalam struktur folder modular dan terorganisasi:

```text
playwright-testing-siaposn/
├── tests/                           # 🧪 Seluruh Skrip Pengujian Playwright
│   ├── functional/                  # 🟢 Skrip Pengujian Fungsional & End-to-End
│   │   ├── end-to-end-flow.spec.ts  # Alur registrasi, pre-test, dan materi
│   │   ├── full-flow-new-account.spec.ts # Alur penuh siswa baru s.d. simulasi 30 soal
│   │   ├── materi-simulasi.spec.ts  # Materi wajib, tandai selesai, simulasi
│   │   ├── register.spec.ts         # Validasi pendaftaran akun
│   │   ├── register-and-simulasi.spec.ts # Alur registrasi ke simulasi
│   │   └── example.spec.ts          # Contoh dasar Playwright
│   └── bug-testing/                 # 🐞 Skrip Verifikasi & Deteksi Bug
│       ├── bug-detection-testqa6.spec.ts # Suite 8 bug akun testqa6@gmail.com
│       └── bug-detection.spec.ts    # Suite verifikasi bug awal
│
├── results/                         # 📊 Hasil Pengujian & Bukti Eksekusi
│   ├── screenshots/                 # Tangkapan layar bukti bug (BUG-01 s.d. BUG-08)
│   ├── artifacts/                   # Output trace & log eksekusi otomatis
│   └── .last-run.json               # Status eksekusi terakhir
│
├── reports/                         # 📋 Dokumen Laporan Pengujian & Panduan
│   ├── LAPORAN_BUG_TESTQA6.md       # Laporan lengkap 8 bug akun testqa6
│   ├── REPORT_TESTING_SIAP_OSN.md   # Laporan pengujian komprehensif SIAP OSN
│   ├── PANDUAN_MENJALANKAN_TESTING.md # Petunjuk eksekusi langkah demi langkah
│   ├── FullFlowReport.docx          # Laporan alur dalam format dokumen Word
│   └── html-report/                 # Laporan visual interaktif Playwright HTML
│
├── scripts/                         # 🛠️ Skrip Bantu & Eksplorasi
│   └── scratch/                     # Kumpulan skrip investigasi DOM & API
│
├── coba1/                           # 📦 Fixture Data Kunci Jawaban
│   └── keys.psv                     # Data 922 soal & kunci jawaban database
│
├── auth-testqa6.json                # 🔑 Sesi autentikasi akun testqa6@gmail.com
├── playwright.config.ts             # ⚙️ Konfigurasi Playwright Test
└── package.json                     # 📦 Skrip NPM & dependensi proyek
```

---

## 🚀 Panduan Eksekusi (Quick Commands)

### 1. Menjalankan Suite Deteksi Bug (Akun `testqa6@gmail.com`)
```bash
npm run test:bugs:qa6
```

### 2. Menjalankan Seluruh Pengujian Bug
```bash
npm run test:bugs
```

### 3. Menjalankan Pengujian Fungsional (End-to-End)
```bash
npm run test:functional
```

### 4. Menjalankan Pengujian Materi & Simulasi
```bash
npm run test:materi
```

### 5. Menjalankan dengan Tampilan Browser (Headed Mode)
```bash
npm run test:headed
```

### 6. Membuka Laporan HTML Playwright
```bash
npm run report:show
```

---

## 📌 Laporan Bug Utama
Laporan detail temuan bug akun `testqa6@gmail.com` dapat dibaca pada:
👉 **[reports/LAPORAN_BUG_TESTQA6.md](reports/LAPORAN_BUG_TESTQA6.md)**
