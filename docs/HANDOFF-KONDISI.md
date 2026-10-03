# Laporan Kondisi Repo — Osn-Readiness-Web

Tujuan: agent lain bisa memverifikasi sendiri setiap klaim di dokumen ini, lalu memutuskan
langkah berikutnya tanpa perlu bertanya.

Semua SHA di bawah diverifikasi pada commit `aa1189c` (fetch terakhir).

---

## 1. Peta branch

| Ref | SHA | Isi | Kondisi |
| --- | --- | --- | --- |
| `origin/dev` | `deac613` | B0-A saja | ✅ sehat |
| `origin/main` | `aa1189c` | B0-A(via PR#1) + B0-B(via PR#2) | ❌ tidak bisa boot |
| `origin/feat/b0a-skema-model` | `8809ece` | B0-A (6 commit) | ✅ sudah merged ke `dev` |
| `origin/feat/b0b-kontrak` | `18dd367` | B0-A + B0-B benar (2 commit) | ✅ referensi |
| `origin/feat/b1a-auth-putaran` | `2404364` | B0-B benar + B1-A (9 commit) | ✅ siap merge |
| `origin/feat/b0b-kontrak-error` | `dfef48f` | B0-B versi salah (2 commit) | ❌ sudah tidak dipakai |
| `origin/feat/b1c-perhitungan` | `c1fbe11` | B1-C (milik agent lain) | ❌ diperiksa: memakai `PerhitunganClientInterface` dan `PermintaanSoal` yang tidak ada, method di luar kontrak. Dikerjakan ulang, lihat `BATCH_PLAN.md` § B1-C |
| `origin/revert-2-...` | `9c6b412` | revert PR#2 | 🔧 PR#5 menunggu merge |

## 2. Status PR

| PR | Base ← Head | Status |
| --- | --- | --- |
| #1 | `main` ← `dev` | merged 2026-10-03 |
| #2 | `main` ← `feat/b0b-kontrak-error` | merged 2026-10-03 ← **menyebabkan `main` rusak** |
| #3 | `dev` ← `feat/b0a-skema-model` | merged 2026-10-03 |
| #4 | `dev` ← `feat/b0b-kontrak-error` | **closed tanpa merge** ✅ |
| #5 | `main` ← revert PR#2 | **open** — diverifikasi benar, silakan merge |

Review lengkap untuk #4 sudah+nempel di PR tersebut.

---

## 3. `dev` — SEHAT (sudah diverifikasi dari nol)

Carakteristik: checkout bersih, database scratch, `vendor` asli (bukan symlink).

```
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=54329 DB_USERNAME=sail DB_PASSWORD=password
```

| Cek | Hasil |
| --- | --- |
| `php artisan migrate:fresh --seed` | ✅ 26 migration |
| Isi DB | 2 tingkat · 32 aturan_pemetaan · 15 materi · **2205 soal** · 15 quiz · 2 simulasi · 2 role · 47 permission |
| View `riwayat_hasil` | ✅ ada |
| Partial unique index | ✅ 4: `users_email_aktif_unique`, `pretest_berjalan_unique`, `hasil_simulasi_berjalan_unique`, `kenaikan_lulus_unique` |
| `php artisan migrate:rollback --step=100` | ✅ 26 rollback, sisa 1 tabel `migrations` |
| `composer test` | ✅ **68/68**, 178 assertion |
| `vendor/bin/pint --test` | ✅ PASS |
| Query di Controller | ✅ 0 (`grep -rE "DB::\|::where\(\|::find\(" app/Http/Controllers`) |
| File B0-B di `dev` | ✅ nol — `app/Exceptions/BisnisException.php`, `app/Services/PutaranService.php`, `app/Randomizers/SeededRandomizer.php` semuanya tidak ada |

## 4. `main` — RUSAK (PR#2), sudah ada perbaikannya

Gejala, direproduksi di worktree bersih pada `origin/main`:

```
$ php artisan migrate:fresh
   Array to string conversion
  at vendor/.../Routing/ResourceRegistrar.php:641
  routes/api.php:74
  → Route::apiResource('tingkat-seleksi', [\App\Http\Controllers\Api\Admin\TingkatSeleksiController::class])
```

Gagal **sebelum menyentuh database**, jadi tidak ada yang bisa jalan dari `main`.

Penyebabnya:

1. `routes/api.php` memanggil `apiResource` dengan array `[Klass::class]`, harusnya string.
2. 9 controller yang dirujuk rute tidak pernah dibuat: seluruh `Api/Siswa/*` dan
   `Api/Admin/*` (Tingkat, Materi, Soal, Pretest, Simulasi, Kelas, Kurikulum).
3. Rute memakai `role:admin`, sedangkan seeder hanya membuat `Super Admin` dan `siswa`.
4. `compose.yaml` masih MySQL, padahal skema butuh PostgreSQL.
5. `RolesAndPermissionsSeeder` masih membuat role `user`, bukan `siswa`.

**PR#5 sudah diverifikasi memperbaiki semuanya:**

```
$ php artisan route:list --path=api   # ✅ 8 rute, fatal error hilang
$ php artisan migrate:fresh           # ✅ 6 migration, selesai
```

Setelah PR#5 di-merge, `main` hidup tapi **tertinggal 94 file / 4549 baris** dari `dev`
— seluruh B0-A belum ada di sana. Perlu PR `dev` → `main` menyusul.

---

## 5. Kalau PR#4 sempat di-resolve — kenapa berbahaya

Ini yang paling penting dipahami. GitHub hanya menampilkan 25 file konflik,
padahal 21 file migration akan **memasuk tanpa konflik** karena nama filenya berbeda
(`2026_09_30_*` vs `2026_10_02_*`).

```
$ git merge-tree --write-tree origin/dev origin/feat/b0b-kontrak-error
$ git ls-tree -r --name-only $TREE -- database/migrations | wc -l
47                       # dev 26 + branch ini 21
$ git merge-tree --write-tree origin/dev origin/feat/b0b-kontrak-error \
    | grep -c '^CONFLICT'
45                       # yang terlihat di GitHub hanya 25
```

Akibatnya 19 tabel + 1 view dibuat dua kali, termasuk
`create_tingkat_seleksi_table` yang di branch itu sudah dobel sendiri
(`000001` dan `000002`).

## 6. Bentuk kontrak yang menyimpang di branch `feat/b0b-kontrak-error`

| Kontrak | Di branch itu | Diminta `docs/Logic per fitur.md` |
| --- | --- | --- |
| `AturanTingkat` | `maxSiswa`, `minNilai`, `syarat` | bobot, persen level pre-test/simulasi, batas, kuota |
| `StatusPutaran` | enum `belum_dimulai/berjalan/selesai` | DTO berisi 8 nilai turunan |
| `TahapSiswa` | 5 case | 7 case: `BELUM_PRETEST`, `PRETEST_BERJALAN`, `BELAJAR`, `SIAP_SIMULASI`, `SIMULASI_BERJALAN`, `PUTARAN_HABIS`, `LULUS` |
| `BisnisException` | menambah field `status: error` | format `{message, kode, detail}` |
| Permission | `tingkat_seleksi.*`, `konteks_soal.*`, `aturan_pemetaan.*` | `tingkat.*`, `konteks-soal.*`, `aturan-pemetaan.*` |
| Rute | `POST /api/putaran/{id}/enroll`, `GET /api/pretest/{id}/hasil`, resource `kelas` & `kurikulum` | `POST /api/pretest`, `POST /api/simulasi/{id}/mulai`, `GET/PUT/POST /api/hasil-simulasi/{id}`, admin di `/api/admin/*` |

Yang boleh dipakai: ide `BisnisException` dengan `kode` + status HTTP, dan
penanganannya di `bootstrap/app.php`.

---

## 7. Implementasi referensi (sudah benar, terverifikasi)

Branch `feat/b0b-kontrak` — 2 commit di atas `8809ece`:

| Commit | Isi |
| --- | --- |
| `03d41bf` | `BisnisException` + 13 subclass kode bisnis + handler global |
| `18dd367` | DTO `AturanTingkat`, `StatusPutaran`, `SyaratSimulasi` · enum `TahapSiswa` · interface `Aturan`/`Putaran`/`SyaratSimulasi`/`Tingkat` |

Branch `feat/b1a-auth-putaran` — 9 commit, tambah 7 commit B1-A:
`AturanService` (cache per request, di-bind `scoped`), `PutaranService` (8 nilai
turun + 7 tahap), `TingkatService`, `SyaratSimulasiService` (kerangka), auth token,
`GET /api/tingkat`, cabut token saat nonaktif, 23 test.

Konvensi yang perlu dijaga supaya tidak beda lagi:

- Enum di `app/Enums/`, DTO di `app/DTOs/`.
- `StatusPutaran` menyimpan id (`pretestBerjalanId`, `putaranAktifId`,
  `simulasiBerjalanId`) selain boolean-nya, supaya pemanggil tidak query ulang.
- Kolom `text` di `aturan_pemetaan` dikonversi ke tipe oleh service, bukan pemanggil.
- Query hanya di Repository. Controller memanggil Interface, `Gate::authorize`,
  balikan lewat API Resource.
- Tabel tidak jamak → setiap model wajib `#[Table('...')]`, `constrained()` selalu
  menyebut nama tabel.

---

## 8. Sisa pekerjaan B0-B

Sudah ada: exception + handler, DTO aturan/status/syarat, enum `TahapSiswa`,
interface Aturan/Putaran/Syarat/Tingkat, `AturanService`, `PutaranService`,
`TingkatService`, skeleton `SyaratSimulasiService`.

Belum ada — **ini yang menahan B1-B, B1-C, dan B3-A**:

| Artefak | Dibutuhkan oleh | Catatan bentuk |
| --- | --- | --- |
| `RandomizerInterface` + `SeededRandomizer` | B1-B | interface agar `SoalPickerService` bisa diuji deterministik dengan seed |
| `SoalPickerServiceInterface` + DTO `PermintaanSoal`, `SoalTerpilih` | B1-B | picker tidak menulis ke DB; mengembalikan bobot + urutan, atau gagal seluruhnya |
| `PerhitunganClientInterface` + `FakePerhitunganClient` | B1-C | `hitungPenilaian`, `hitungPretest`; retry hanya koneksi/timeout/5xx; 403/422 → 502 |
| `PenilaianServiceInterface` | B2-A, B2-B, B3-A | `selesaikanPenilaian(int $id)`, idempoten, dipanggil `NilaiUlangJob` |
| `KelulusanServiceInterface` | B3-A | dipakai di dalam transaksi kedua saat simulasi dinilai |

Perhatikan `SyaratSimulasiServiceInterface::periksa()` sudah ada dan return DTO
`SyaratSimulasi { bool terpenuhi, array rincian }` — B2-B wajib conforms ke bentuk ini.

---

## 9. Keputusan terbuka yang butuh jawaban

1. **Rute admin.** Modul users sekarang di `/api/users` (bawaan repo lama). B1-D
  apakah dipindahkan ke `/api/admin/siswa` sesuai dokumen? Belum diputuskan.
2. **Bentuk `PerhitunganClientInterface` dan `PenilaianServiceInterface`.** Kalau
   agent lain mulai B1-C sebelum kontrak ini disepakati, kemungkinan ada rework.
3. **`KuotaLevel::hitung()`** ditempatkan di B1-B (algoritma picker), bukan B0-B.
4. **`TahapSiswa` di `app/Enums/`**, bukan `app/DTOs/` seperti usulan BATCH_PLAN.

---

## 10. Peringatan teknis (bikin salah 판단 kalau dilupakan)

- **PostgreSQL 16 only.** Skema memakai index unik parsial, `jsonb`, dan check
  constraint. SQLite dan MySQL tidak bisa dipakai, termasuk untuk test.
- **Database test terpisah** `osn_readiness_testing` (lihat `phpunit.xml`).
  Jangan pernah pakai database pengembangan untuk test.
- **Jangan pakai symlink `vendor` untuk verifikasi lewat worktree.** Autoloader `App\`
  akan menunjuk repo utama dan hasil test jadi menyesatkan — ini sudah terjadi
  dua kali. Verifikasi `dev`/`main` dengan checkout langsung di repo utama.
- **`.env` hasil salinan tidak boleh dipakai apa adanya.** Repo Laravel memakai env repository
  *immutable*, jadi baris pertama yang mendefinisikan suatu kunci menang. Kalau
  menyalin `.env`, pastikan baris pertama tiap kunci sudah benar, atau export
  env eksplisit di shell.
- `SESSION_DRIVER=cookie` (tabel `sessions` di-drop migration 1, Breeze web tetap dipakai).
- Partial unique index harus lewat `DB::statement`; Laravel tidak punya index parsial.
- `riwayat_hasil` adalah SQL view, migration-nya wajib jalan paling akhir.
- `Soal` memakai `SoftDeletes`, dan `kunci_jawaban` tidak boleh masuk `SoalResource`.
- Role default adalah `siswa`, bukan `user`.

---

## 11. Perintah verifikasi cepat

```bash
git fetch --all --prune
git log --oneline origin/dev -3

# dev sehat?
git switch dev && git merge --ff-only origin/dev
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=54329 DB_USERNAME=sail DB_PASSWORD=password \
  sh -c 'php artisan config:clear && composer test && vendor/bin/pint --test'
git switch feat/b1a-auth-putaran    # kembali

# main rusak?
git worktree add -q --detach /tmp/chk origin/main
cd /tmp/chk && ln -s <path-repo>/vendor vendor && cp <path-repo>/.env .env
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=54329 DB_DATABASE=chk DB_USERNAME=sail DB_PASSWORD=password \
  php artisan migrate:fresh      # expect: Array to string conversion

# simulate merge PR#4 (jangan dijalankan sungguhan)
git merge-tree --write-tree origin/dev origin/feat/b0b-kontrak-error \
  | grep -c '^CONFLICT'          # 45
```

## 12. Saran urutan merge

1. Merge PR#5 → `main` hidup kembali.
2. Merge `feat/b0b-kontrak` → `dev` (2 commit, tanpa konflik).
3. Merge `feat/b1a-auth-putaran` → `dev` (7 commit tersisa setelah no.2).
4. Merge `dev` → `main` supaya `main` ikut punya B0-A.
5. Tutup `origin/feat/b1c-perhitungan`; B1-C dibangun ulang sebagai `feat/b1c-perhitungan-v2` dari `dev` dengan kontrak di `BATCH_PLAN.md` § B1-C.