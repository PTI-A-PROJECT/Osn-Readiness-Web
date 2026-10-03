# Batch Plan — Implementasi Backend OSN Readiness

Sumber: [Logic per fitur.md](Logic%20per%20fitur.md), [Migration.pdf](Migration.pdf), [Kode migration.pdf](Kode%20migration.pdf). Semua kode mengikuti [ARCHITECTURE_RULES.md](ARCHITECTURE_RULES.md) (Controller → Service → Repository, Interface di `app/Contracts`, binding di `RepositoryServiceProvider`).

## Cara membaca

- Batch dikerjakan **berurutan**. Paket di dalam satu batch (B1-A, B1-B, ...) **bisa dikerjakan paralel** oleh orang yang berbeda.
- Satu paket = satu branch `feat/<kode-paket>-<nama>` (contoh `feat/b1a-putaran`) = satu PR ke `dev`.
- Paket boleh dimulai bila semua paket di kolom "Butuh" sudah di-merge ke `dev`.
- Kode BE-xx merujuk ke bagian di Logic per fitur.

## Peta dependensi

```
B0-A Skema & Model ──┬──> B1-A Auth, Aturan & Putaran ──┬──> B2-A Pre-test ───────────┐
B0-B Kontrak & Error ┤    B1-B SoalPicker ──────────────┤    B2-B Materi, Latihan,    ├──> B3-A Simulasi & Kelulusan ──┐
                     │    B1-C Klien Python & Job ──────┘         Syarat ─────────────┤    B3-B Dashboard & Riwayat ───┼──> B4 Integrasi
                     ├──> B1-D Admin: Struktur konten ──┬──> B2-C Bank soal & Admin dashboard   B3-C Purge akun ────────┘
                     └──> B1-E Admin: Soal & Konfigurasi┴──> B2-D Impor konten
```

## Pembagian (2 orang)

| Orang | Fokus | Paket | Rincian |
| --- | --- | --- | --- |
| 1 | Alur siswa inti | B0-A, B1-A, B1-B, B2-A, B3-A, B3-C, B4 (test skenario) | [BATCH_PLAN_ORANG_1.md](BATCH_PLAN_ORANG_1.md) |
| 2 | Infrastruktur, admin, fitur pendukung | B0-B, B1-C, B1-E, B1-D, B2-B, B2-C, B2-D, B3-B, B4 (route check & docs) | [BATCH_PLAN_ORANG_2.md](BATCH_PLAN_ORANG_2.md) |

Titik serah terima utama: B0-A ↔ B0-B (keduanya wajib merge dulu), B1-C → B2-A, B1-A/B1-B → B2-B, dan B2-B → B3-A.

## Aturan kerja paralel (hindari konflik merge)

- **File bersama** (`routes/api.php`, `RepositoryServiceProvider`, `RolesAndPermissionsSeeder`, `bootstrap/app.php`, `app/Exceptions/*`): B0-B menyiapkan blok berlabel per paket. Tiap paket hanya menambah baris di blok miliknya.
- Kontrak di `app/Contracts/**` dibuat di B0-B. Mengubah signature setelah B0 harus disetujui pemilik paket yang memakainya.
- Test memakai PostgreSQL (partial index, `jsonb`, dan check constraint tidak jalan di sqlite). Python selalu di-fake (`Http::fake`) atau pakai `PerhitunganClientInterface` palsu.
- DoD tiap paket: Feature test (matriks 401/403/422/sukses + kode error bisnis), Unit test untuk service dengan logika, `vendor/bin/pint --test` bersih, `composer test` hijau, tidak ada query di Controller.

---

## Batch 0 — Fondasi (blocking, harus merge dulu)

### B0-A · Postgres, migration, model, seeder

**Butuh:** —

- Ganti service `mysql` di `compose.yaml` ke `postgres:16`; `DB_CONNECTION=pgsql` di `.env.example` dan `phpunit.xml` (database test terpisah).
- 19 migration sesuai Kode migration, berurutan (users → tingkat_seleksi → ... → view `riwayat_hasil` paling akhir):
  - Migration 1 menyesuaikan repo: `is_active` sudah ada (lewati via `hasColumn`), softDeletes, index parsial `users_email_aktif_unique`. Lihat keputusan terbuka #1 soal tabel `sessions`.
  - Check constraint dan index parsial lewat `DB::statement`; `down()` lengkap.
- Model untuk 20 tabel: `$table` eksplisit (nama tidak jamak), relasi, casts, `SoftDeletes` di `User` dan `Soal`.
- Enum PHP di `app/Enums`: `Level`, `Peruntukan`, `TipeSoal`, `StatusProgress`, `StatusKenaikan`, `JenisPengerjaan`.
- Model `RiwayatHasil` (baca saja, tanpa timestamps) di atas view.
- Factory untuk semua model konten dan pengerjaan.
- Seeder: `TingkatSeleksiSeeder`, `AturanPemetaanSeeder` (32 baris, `firstOrCreate`), `KontenContohSeeder` (lokal/test: 5 materi Kabupaten, 10 Provinsi, bank cukup untuk 3 putaran pre-test + 1 simulasi + 1 latihan per materi). Update `RolesAndPermissionsSeeder`: role `siswa` menggantikan `user` dan permission `{resource}.{ability}` untuk semua resource admin.

**Selesai bila:** `php artisan migrate:fresh --seed` sukses di Postgres; `migrate:rollback` sampai bawah juga sukses.

### B0-B · Kontrak, error handling, kerangka bersama

**Butuh:** — (paralel dengan B0-A; cukup merujuk nama kelas model)

- Revisi `ARCHITECTURE_RULES.md`: §3.5 (Service boleh bergantung pada Client interface) dan §8 (role `siswa`).
- Exception bisnis dasar, misalnya `App\Exceptions\BisnisException` dengan `kode` (string) dan `status` HTTP, dirender global di `bootstrap/app.php` menjadi `{ "message", "kode", "detail" }`. Daftar kode: `TINGKAT_TERKUNCI`, `SUDAH_LULUS`, `PUTARAN_MASIH_BERJALAN`, `BELUM_PRETEST`, `SYARAT_SIMULASI_BELUM_TERPENUHI`, `KUOTA_SIMULASI_HABIS`, `SIMULASI_BELUM_DINILAI`, `WAKTU_HABIS`, `BANK_SOAL_TIDAK_CUKUP` (503), `HASIL_SEDANG_DIPROSES` (503), `LAYANAN_HITUNG_SALAH_KONFIGURASI` (502).
- Interface + DTO (tanpa implementasi) supaya B1 bisa paralel:
  - `AturanServiceInterface` → DTO `AturanTingkat` (bobot, persen level pre-test/simulasi, batas, kuota).
  - `PutaranServiceInterface` → DTO `StatusPutaran` (8 nilai turunan + enum `TahapSiswa`).
  - `SoalPickerServiceInterface` → DTO `PermintaanSoal` / `SoalTerpilih`; `RandomizerInterface`.
  - `SyaratSimulasiServiceInterface`, `KelulusanServiceInterface`.
  - `PerhitunganClientInterface` (`hitungPenilaian`, `hitungPretest`).
  - `PenilaianServiceInterface` dengan `selesaikanPenilaian(int $id)`, untuk Pretest/Latihan/Simulasi dan dipanggil `NilaiUlangJob`.
- Kerangka `routes/api.php`: grup `auth:sanctum` + `active`, grup siswa, grup `admin` (prefix `/api/admin`, role Super Admin), dengan blok komentar per paket. Blok binding per paket di `RepositoryServiceProvider`.
- Fake bersama untuk test: `FakePerhitunganClient` dan `SeededRandomizer`.

**Selesai bila:** semua interface ada dan ter-bind ke stub, handler mengembalikan format error di atas (diuji satu test).

---

## Batch 1 — Service inti & admin dasar (paralel)

### B1-A · Auth, AturanService, PutaranService (BE-01, BE-02)

**Butuh:** B0-A, B0-B

- Sesuaikan `AuthService` yang sudah ada: register (role `siswa`, 201 + token), login manual (tanpa `Auth::attempt`), `Hash::check`, cek `is_active` (403), pesan 401 yang sama untuk email/password salah, throttle per email+IP (429), token dengan masa berlaku, logout hanya token aktif, `me` mengembalikan `tingkat_aktif_id`.
- Nonaktif/soft delete mencabut semua token (hook di `UserService`).
- `AturanService`: baca `aturan_pemetaan` per tingkat menjadi DTO bertipe (cache per request).
- `PutaranService`: turunkan 8 nilai (tingkat_terbuka, sudah_lulus, pretest_berjalan, putaran_aktif, percobaan_terpakai, simulasi_berjalan, putaran_habis, boleh_pretest_baru) dan `TahapSiswa`. Tidak ada kolom status.
- `GET /api/tingkat` → dua tingkat + `tingkat_terbuka` + `tahap`.

**Test:** unit test `PutaranService` untuk ketujuh tahap; feature test auth lengkap.

### B1-B · SoalPickerService (BE-03)

**Butuh:** B0-A, B0-B

- Fungsi kuota level terpisah (misalnya `KuotaLevel::hitung(jumlah, persen[])`: floor + sisa ke pecahan terbesar; 30 → 15/9/6). **Dipakai ulang oleh B2-C.**
- Langkah 1–7: kandidat (tingkat, peruntukan, belum dihapus, minus dikecualikan), batas materi pre-test (2 per materi, level dengan sisa kuota terbanyak), isi sisa, prioritas non-dihindari untuk simulasi, `BankSoalTidakCukupException` dengan rincian, acak urutan, pasang bobot.
- Tidak menulis ke DB; Randomizer disuntikkan.
- `SoalResource` (tanpa kunci) dan `SoalReviewResource` (dengan kunci dan pembahasan), termasuk isi cerita bila ada `konteks_id` dan URL gambar. Ditaruh di sini supaya B2-A tidak menunggu B1-E.

**Test:** unit test deterministik dengan seed untuk Kabupaten (10+20) dan Provinsi (20+10), kasus kurang per level, kurang per materi, dan prioritas soal dihindari.

### B1-C · PerhitunganClient & NilaiUlangJob (BE-11)

**Butuh:** B0-B

- `config/services.php` → `perhitungan.url/token/timeout(5)/retry(2)`; env di `.env.example`.
- Header `X-Internal-Token`; retry hanya untuk koneksi, timeout, dan 5xx.
- Pemetaan error: gagal sementara → `PerhitunganTidakTersediaException` (503); 403/422 atau balasan tidak cocok → `PerhitunganKonfigurasiException` (502, log critical).
- Validasi balasan: jumlah jawaban sama, setiap materi punya pemetaan, jumlah materi wajib sesuai aturan.
- `NilaiUlangJob(jenis, id)`: resolve `PenilaianServiceInterface` per jenis, 5 percobaan dengan backoff `[10,30,60,120,300]`, idempoten.
- Test kontrak: contoh request/response dari dokumen alur dibandingkan dengan payload yang dikirim client.

### B1-D · Admin: struktur konten (BE-12..14 sebagian)

**Butuh:** B0-A, B0-B

CRUD `/api/admin/*` per checklist ARCHITECTURE_RULES §10 untuk:

- **Tingkat**: hanya ubah nama/deskripsi; tidak ada store/destroy.
- **Kompetensi**: nama unik per tingkat; hapus ditolak bila punya materi (409).
- **Materi**: kompetensi harus dari tingkat yang sama; urutan unik per tingkat; hapus ditolak bila punya soal, latihan, atau dirujuk hasil siswa.
- **Cerita soal (konteks_soal)**: tingkat wajib; hapus ditolak bila dipakai soal.
- **Upload gambar materi**: `POST /api/admin/materi/gambar` (png/jpg/webp, maks 2 MB), balas path relatif tanpa domain.
- **Siswa**: list, nonaktifkan, soft delete (mencabut token; pakai hook dari B1-A, atau koordinasi).

### B1-E · Admin: soal & konfigurasi (BE-17..20)

**Butuh:** B0-A, B0-B

- **Soal**: materi dan cerita setingkat; pilihan ganda minimal 2 pilihan dan kunci ada di pilihan; isian tanpa pilihan; hapus = soft delete. Bila soal sudah dipakai pengerjaan, kunci/level/peruntukan/materi tidak boleh berubah (teks boleh). Taruh aturan ini di satu kelas (misalnya `SoalGuard`) karena **dipakai ulang oleh B2-D**.
- **Pembahasan**: satu per soal, upsert.
- **Latihan (quiz)**: satu per materi; `jumlah_soal >= latihan_min_soal`; hapus ditolak bila punya pengerjaan.
- **Simulasi**: `jumlah_soal`, `durasi_menit` > 0; hapus ditolak bila punya hasil. Guard `is_aktif` dipasang di B2-C.
- **Aturan pemetaan**: hanya ubah nilai; persen level berjumlah 100; bobot ≥ 1; passing_grade dan latihan_min_nilai 0–100; jumlah_materi_wajib ≤ jumlah materi; min_soal_per_materi × jumlah materi ≤ pretest_jumlah_soal.

---

## Batch 2 — Alur siswa & alat admin

### B2-A · Pre-test & pemetaan (BE-04, BE-05)

**Butuh:** B1-A, B1-B, B1-C

- `POST /api/pretest`, `GET /api/pretest/{id}`, `PUT .../jawaban`, `POST .../submit` sesuai langkah di dokumen (403/409, kembalikan yang berjalan, tangkap pelanggaran `pretest_berjalan_unique`, isi `tingkat_aktif_id`).
- Submit dua transaksi dengan Python di antaranya; `PretestService::selesaikanPenilaian` idempoten (implementasi `PenilaianServiceInterface`), menyimpan `status_benar`, nilai, `pemetaan_materi`, `rekomendasi_materi`.
- Akses milik orang lain → 404.

**Test:** alur penuh dengan Python palsu, Python gagal → 503 + job ter-dispatch, submit dua kali, soal pre-test kedua tidak mengulang soal pertama.

### B2-B · Materi, progress, latihan, syarat simulasi (BE-06, BE-07, BE-08)

**Butuh:** B1-A, B1-B, B1-C, B1-E

- `GET /api/materi`, `GET /api/materi/{id}`, `PUT /api/materi/{id}/progress` (cek tingkat terbuka; tanpa putaran aktif → 409 `BELUM_PRETEST`; wajib dulu, urut prioritas).
- Latihan: mulai/lanjutkan, simpan jawaban, submit dengan pola dua transaksi; `LatihanService::selesaikanPenilaian`; nilai terbaik = maksimum pengerjaan selesai.
- `SyaratSimulasiService` + `GET /api/simulasi/syarat` dengan rincian per materi dan `latihan_belum_tersedia`.

**Test:** putaran aktif bisa dibuat lewat factory, jadi paket ini tidak perlu menunggu B2-A.

### B2-C · Kecukupan bank soal & dashboard admin

**Butuh:** B1-B (fungsi kuota), B1-D, B1-E

- `BankSoalService` + `GET /api/admin/bank-soal/kecukupan?tingkat_id=` (5 bagian; ambang putaran pre-test < 2).
- Guard `is_aktif` simulasi: hanya bisa dinyalakan bila bank cukup untuk satu percobaan.
- `GET /api/admin/dashboard`: siswa aktif, pengerjaan per jenis, siswa per tingkat aktif, rata-rata nilai per jenis.

### B2-D · Impor konten (BE-21)

**Butuh:** B1-D, B1-E (`SoalGuard`)

- `php artisan impor:konten {folder} --dry-run`: parse frontmatter Markdown materi, buat kompetensi bila belum ada, salin gambar materi dan soal ke storage publik, dedup konteks per tingkat, upsert lewat `id_sumber`, pembahasan.
- Validasi sesuai tabel pemeriksaan (id_sumber kembar → batal semua; lainnya → soal ditolak; perubahan terlarang pada soal terpakai → dilewati dan dilaporkan).
- Laporan: masuk, diperbarui, ditolak beserta alasan.
- Perlu kolom `id_sumber` di `materi` dan `soal` (belum ada di migration). Lihat keputusan terbuka #4.

---

## Batch 3 — Simulasi & tampilan baca

### B3-A · Simulasi, kelulusan, penutupan otomatis (BE-09, BE-10)

**Butuh:** B2-A, B2-B

- `GET /api/simulasi`, `POST /api/simulasi/{id}/mulai`, `GET/PUT/POST /api/hasil-simulasi/{id}[...]`, `GET .../review`.
- Mulai dalam satu transaksi dengan `lockForUpdate` pada pretest putaran aktif, urutan cek 1–9 sesuai dokumen; daftar dihindari diteruskan ke picker; `batas_pada` disimpan.
- Simpan jawaban: 409 `WAKTU_HABIS` setelah `batas_pada` + 30 detik.
- Submit dua transaksi; `SimulasiService::selesaikanPenilaian` memanggil `KelulusanService` di transaksi ke-2 (lulus → `kenaikan_tingkat`; gagal ke-3 → hapus progress dan quiz_pengerjaan tingkat itu + `kenaikan_tingkat` tidak_lulus).
- Command `simulasi:tutup-kedaluwarsa` tiap menit, `withoutOverlapping`, didaftarkan di `routes/console.php`.

**Test:** lulus di percobaan 1; gagal 3 kali → data terhapus dan pre-test baru boleh; dua request mulai bersamaan; scheduler menutup simulasi kedaluwarsa; nilai via job tetap memicu kelulusan.

### B3-B · Dashboard & riwayat siswa (BE-15, BE-16)

**Butuh:** B2-B (bisa mulai setelah B2-B; bagian simulasi diisi setelah B3-A merge)

- `DashboardService` menyusun data dari `PutaranService` + `SyaratSimulasiService` saja (tanpa hitungan sendiri).
- `GET /api/riwayat?jenis=&tingkat_id=` dari model `RiwayatHasil`, terbaru dulu, paginated.

### B3-C · PurgeAkunJob

**Butuh:** B0-A

- Job terjadwal: force delete user yang soft-deleted lebih lama dari masa retensi (cascade menghapus pengerjaan). Masa retensi: lihat keputusan terbuka #5.

---

## Batch 4 — Integrasi & penutupan

**Butuh:** semua paket

- Satu feature test skenario penuh: register → pre-test → belajar + latihan → simulasi gagal 3x → pre-test baru → lulus Kabupaten → Provinsi terbuka.
- `php artisan route:list --path=api`: semua rute siswa di balik `auth:sanctum` + `active`, rute admin di balik role/permission.
- `grep -rE "DB::|::where\(|::find\(" app/Http/Controllers` kosong.
- Update `PRD.md`, `IMPLEMENTATION_PLAN.md`, dan README (cara menjalankan Postgres, queue worker, scheduler).

---

## Keputusan terbuka (putuskan sebelum/selama B0)

1. **Tabel `sessions` di-drop di migration 1**, padahal Breeze (web) dari commit sebelumnya memakai session. Pilihan: ubah `SESSION_DRIVER` ke `cookie`/`file`, atau buang Breeze web bila frontend sepenuhnya Vue + token.
2. **Role `user` → `siswa`**: rename di seeder, `UserService::registerUser`, test, dan ARCHITECTURE_RULES §8.
3. **Angka usulan**: throttle login (5/menit), toleransi waktu simulasi (30 detik), backoff job, ukuran upload (2 MB), ambang peringatan bank (2 putaran), masa berlaku token.
4. **`id_sumber`** untuk impor belum ada di skema `materi`/`soal`. Tambah migration ke-20 (nullable, unique per tabel) di B0-A, atau simpan pemetaan di tabel terpisah.
5. **Masa retensi** untuk PurgeAkunJob belum ditentukan.
6. **Layanan Python** (`/hitung/penilaian`, `/hitung/pretest`) di luar repo ini; perlu pemilik terpisah dan contoh kontrak final untuk test kontrak B1-C.
