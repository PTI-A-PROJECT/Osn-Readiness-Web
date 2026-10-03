# Batch Plan — Orang 2: Infrastruktur, Admin & Fitur Pendukung

Bagian dari [BATCH_PLAN.md](BATCH_PLAN.md). Pasangan kerja: [BATCH_PLAN_ORANG_1.md](BATCH_PLAN_ORANG_1.md).

Sumber: [Logic per fitur.md](Logic%20per%20fitur.md), [Migration.pdf](Migration.pdf), [Kode migration.pdf](Kode%20migration.pdf). Semua kode mengikuti [ARCHITECTURE_RULES.md](ARCHITECTURE_RULES.md).

## Peran

Kamu memegang kerangka bersama (kontrak, format error, routes), klien Python, seluruh menu admin, materi dan latihan siswa, syarat simulasi, dashboard, riwayat, dan impor konten. Jumlah paketmu lebih banyak, tetapi sebagian besar CRUD dan penyusunan data. Aturan bisnis yang berat ada di sisi Orang 1.

## Urutan kerja

| # | Paket | Butuh dari Orang 1 | Ditunggu Orang 1 untuk |
| --- | --- | --- | --- |
| 1 | B0-B · Kontrak, error handling, kerangka | — | Semua paketnya (wajib merge paling awal) |
| 2 | B1-C · PerhitunganClient & NilaiUlangJob | — | B2-A |
| 3 | B1-E · Admin: soal & konfigurasi | B0-A, B1-B (SoalResource) | — |
| 4 | B1-D · Admin: struktur konten & siswa | B0-A, B1-A (cabut token) | — |
| 5 | B2-B · Materi, latihan, syarat simulasi | B1-A, B1-B | **B3-A (prioritaskan)** |
| 6 | B2-C · Kecukupan bank soal & dashboard admin | B1-B (fungsi kuota) | — |
| 7 | B2-D · Impor konten | B0-A (`id_sumber`) | — |
| 8 | B3-B · Dashboard & riwayat siswa | B1-A; B3-A untuk bagian simulasi | — |
| 9 | B4 · Route check & dokumentasi | Semua paket Orang 1 | — |

B2-B ada di jalur kritis karena Orang 1 butuh `SyaratSimulasiService` untuk B3-A. Bila B1-A dan B1-B sudah merge, kerjakan B2-B sebelum menyelesaikan admin.

Bila B1-B belum merge saat kamu mulai B1-E, buat dulu CRUD dan validasinya. Respons soal admin menyusul setelah `SoalResource` tersedia.

## Aturan kerja

- Satu paket = satu branch `feat/<kode-paket>-<nama>` (contoh `feat/b1c-perhitungan`) = satu PR ke `dev`. Orang 1 me-review PR-mu, dan sebaliknya.
- File bersama (`routes/api.php`, `RepositoryServiceProvider`, `RolesAndPermissionsSeeder`, `bootstrap/app.php`): kamu yang menyiapkan blok berlabel per paket di B0-B. Setelah itu tulis hanya di blok paketmu.
- Signature di `app/Contracts/**` kamu buat di B0-B. Perubahan setelahnya harus disepakati berdua.
- Test memakai PostgreSQL. Python selalu di-fake.
- DoD tiap paket: Feature test (401/403/422/sukses + kode error bisnis), Unit test untuk service berlogika, `vendor/bin/pint --test` bersih, `composer test` hijau, tidak ada query di Controller.

---

## 1. B0-B · Kontrak, error handling, kerangka bersama

**Butuh:** — (paralel dengan B0-A milik Orang 1; cukup merujuk nama kelas model)

- Revisi `ARCHITECTURE_RULES.md`: §3.5 (Service boleh bergantung pada Client interface) dan §8 (role `siswa`).
- Exception bisnis dasar, misalnya `App\Exceptions\BisnisException` dengan `kode` (string) dan `status` HTTP, dirender global di `bootstrap/app.php` menjadi `{ "message", "kode", "detail" }`. Daftar kode: `TINGKAT_TERKUNCI`, `SUDAH_LULUS`, `PUTARAN_MASIH_BERJALAN`, `BELUM_PRETEST`, `SYARAT_SIMULASI_BELUM_TERPENUHI`, `KUOTA_SIMULASI_HABIS`, `SIMULASI_BELUM_DINILAI`, `WAKTU_HABIS`, `BANK_SOAL_TIDAK_CUKUP` (503), `HASIL_SEDANG_DIPROSES` (503), `LAYANAN_HITUNG_SALAH_KONFIGURASI` (502).
- Interface + DTO (tanpa implementasi) supaya Batch 1 bisa paralel:
  - `AturanServiceInterface` → DTO `AturanTingkat` (bobot, persen level pre-test/simulasi, batas, kuota).
  - `PutaranServiceInterface` → DTO `StatusPutaran` (8 nilai turunan + enum `TahapSiswa`).
  - `SoalPickerServiceInterface` → DTO `PermintaanSoal` / `SoalTerpilih`; `RandomizerInterface`.
  - `SyaratSimulasiServiceInterface`, `KelulusanServiceInterface`.
  - `PerhitunganClientInterface` (`hitungPenilaian`, `hitungPretest`).
  - `PenilaianServiceInterface` dengan `selesaikanPenilaian(int $id)`, untuk Pretest/Latihan/Simulasi dan dipanggil `NilaiUlangJob`.
- Kerangka `routes/api.php`: grup `auth:sanctum` + `active`, grup siswa, grup `admin` (prefix `/api/admin`, role Super Admin), dengan blok komentar per paket. Blok binding per paket di `RepositoryServiceProvider`.
- Fake bersama untuk test: `FakePerhitunganClient` dan `SeededRandomizer`.

**Selesai bila:** semua interface ada dan ter-bind ke stub, handler mengembalikan format error di atas (diuji satu test).

## 2. B1-C · PerhitunganClient & NilaiUlangJob (BE-11)

**Butuh:** B0-B

- `config/services.php` → `perhitungan.url/token/timeout(5)/retry(2)`; env di `.env.example`.
- Header `X-Internal-Token`; retry hanya untuk koneksi, timeout, dan 5xx.
- Pemetaan error: gagal sementara → `PerhitunganTidakTersediaException` (503); 403/422 atau balasan tidak cocok → `PerhitunganKonfigurasiException` (502, log critical).
- Validasi balasan: jumlah jawaban sama, setiap materi punya pemetaan, jumlah materi wajib sesuai aturan.
- `NilaiUlangJob(jenis, id)`: resolve `PenilaianServiceInterface` per jenis (pretest dan simulasi diisi Orang 1, latihan olehmu di B2-B), 5 percobaan dengan backoff `[10,30,60,120,300]`, idempoten.
- Test kontrak: contoh request/response dari dokumen alur dibandingkan dengan payload yang dikirim client.

## 3. B1-E · Admin: soal & konfigurasi (BE-17..20)

**Butuh:** B0-A; B1-B untuk `SoalResource`/`SoalReviewResource`

- **Soal**: materi dan cerita setingkat; pilihan ganda minimal 2 pilihan dan kunci ada di pilihan; isian tanpa pilihan; hapus = soft delete. Bila soal sudah dipakai pengerjaan, kunci/level/peruntukan/materi tidak boleh berubah (teks boleh). Taruh aturan ini di satu kelas (misalnya `SoalGuard`) karena **dipakai ulang di B2-D**.
- **Pembahasan**: satu per soal, upsert.
- **Latihan (quiz)**: satu per materi; `jumlah_soal >= latihan_min_soal`; hapus ditolak bila punya pengerjaan.
- **Simulasi**: `jumlah_soal`, `durasi_menit` > 0; hapus ditolak bila punya hasil. Guard `is_aktif` dipasang di B2-C.
- **Aturan pemetaan**: hanya ubah nilai; persen level berjumlah 100; bobot ≥ 1; passing_grade dan latihan_min_nilai 0–100; jumlah_materi_wajib ≤ jumlah materi; min_soal_per_materi × jumlah materi ≤ pretest_jumlah_soal.

## 4. B1-D · Admin: struktur konten & siswa (BE-12..14)

**Butuh:** B0-A; B1-A untuk pencabutan token

CRUD `/api/admin/*` per checklist ARCHITECTURE_RULES §10 untuk:

- **Tingkat**: hanya ubah nama/deskripsi; tidak ada store/destroy.
- **Kompetensi**: nama unik per tingkat; hapus ditolak bila punya materi (409).
- **Materi**: kompetensi harus dari tingkat yang sama; urutan unik per tingkat; hapus ditolak bila punya soal, latihan, atau dirujuk hasil siswa.
- **Cerita soal (konteks_soal)**: tingkat wajib; hapus ditolak bila dipakai soal.
- **Upload gambar materi**: `POST /api/admin/materi/gambar` (png/jpg/webp, maks 2 MB), balas path relatif tanpa domain.
- **Siswa**: list, nonaktifkan, soft delete; pencabutan token memakai method `UserService` dari B1-A.

## 5. B2-B · Materi, progress, latihan, syarat simulasi (BE-06, BE-07, BE-08)

**Butuh:** B1-A, B1-B, B1-C

- `GET /api/materi`, `GET /api/materi/{id}`, `PUT /api/materi/{id}/progress` (cek tingkat terbuka; tanpa putaran aktif → 409 `BELUM_PRETEST`; wajib dulu, urut prioritas).
- Latihan: mulai/lanjutkan, simpan jawaban, submit dengan pola dua transaksi; `LatihanService::selesaikanPenilaian`; nilai terbaik = maksimum pengerjaan selesai.
- `SyaratSimulasiService` + `GET /api/simulasi/syarat` dengan rincian per materi dan `latihan_belum_tersedia`.

**Test:** putaran aktif dibuat lewat factory, jadi tidak perlu menunggu B2-A milik Orang 1.

## 6. B2-C · Kecukupan bank soal & dashboard admin

**Butuh:** B1-B (fungsi kuota), B1-D, B1-E

- `BankSoalService` + `GET /api/admin/bank-soal/kecukupan?tingkat_id=` (5 bagian; ambang putaran pre-test < 2). Wajib memakai fungsi kuota yang sama dengan SoalPicker.
- Guard `is_aktif` simulasi: hanya bisa dinyalakan bila bank cukup untuk satu percobaan.
- `GET /api/admin/dashboard`: siswa aktif, pengerjaan per jenis, siswa per tingkat aktif, rata-rata nilai per jenis.

## 7. B2-D · Impor konten (BE-21)

**Butuh:** B1-D, B1-E (`SoalGuard`), kolom `id_sumber` dari B0-A

- `php artisan impor:konten {folder} --dry-run`: parse frontmatter Markdown materi, buat kompetensi bila belum ada, salin gambar materi dan soal ke storage publik, dedup konteks per tingkat, upsert lewat `id_sumber`, pembahasan.
- Validasi sesuai tabel pemeriksaan (id_sumber kembar → batal semua; lainnya → soal ditolak; perubahan terlarang pada soal terpakai → dilewati dan dilaporkan).
- Laporan: masuk, diperbarui, ditolak beserta alasan.

## 8. B3-B · Dashboard & riwayat siswa (BE-15, BE-16)

**Butuh:** B1-A, B2-B; bagian simulasi diisi setelah B3-A merge

- `DashboardService` menyusun data dari `PutaranService` + `SyaratSimulasiService` saja (tanpa hitungan sendiri).
- `GET /api/riwayat?jenis=&tingkat_id=` dari model `RiwayatHasil`, terbaru dulu, paginated.

## 9. B4 · Route check & dokumentasi (bersama Orang 1)

**Butuh:** semua paket

- `php artisan route:list --path=api`: semua rute siswa di balik `auth:sanctum` + `active`, rute admin di balik role/permission.
- `grep -rE "DB::|::where\(|::find\(" app/Http/Controllers` kosong.
- Update `PRD.md`, `IMPLEMENTATION_PLAN.md`, dan README (cara menjalankan Postgres, queue worker, scheduler).

---

## Keputusan terbuka yang kamu pegang

| # | Keputusan | Kapan |
| --- | --- | --- |
| 4 | Kolom `id_sumber` untuk impor: migration ke-20 atau tabel pemetaan terpisah (sepakati dengan Orang 1, yang menulis migration-nya) | Selama B0 |
| 6 | Layanan Python (`/hitung/penilaian`, `/hitung/pretest`) di luar repo: siapa pemiliknya, dan contoh kontrak final untuk test kontrak | Sebelum B1-C |
| 3 | Angka usulan milikmu: backoff job, ukuran upload (2 MB), ambang peringatan bank (2 putaran) | Selama B1-C / B1-D / B2-C |
