# Audit Beban Server dan Request Berulang (FE + BE + Infra)

Tujuan: menilai apakah aplikasi sanggup dipakai banyak siswa serentak dan apa yang
perlu diperbaiki sebelum uji coba besar. Audit ini **baca kode saja**: tidak ada beban
yang diukur, jadi semua angka adalah perkiraan dari kode. Bagian bertanda
**[INFERENSI]** tidak bisa dibuktikan dari repo (misalnya perilaku bawaan Sanctum,
default image Apache, ukuran VPS yang tidak disebut di docs mana pun).

Poin kunci yang saya cek ulang langsung di kode: `cors.max_age = 0`, tidak ada satu pun
`Cache::` di `app/`, tidak ada `config:cache`/`route:cache` di `Dockerfile.production`,
`AppLayout` memanggil `fetchDashboard()` di setiap masuk halaman siswa, dan `/auth/me`
yang gagal menghapus token. Satu temuan agen **dikoreksi**: `queue-worker` memang ada
di profile `worker`, tetapi `scripts/deploy-vps.sh:61` sudah menjalankannya dengan
`--profile worker`, jadi bukan masalah.

## Ringkasan

| # | Temuan | Dampak | Usaha |
|---|---|---|---|
| 1 | Preflight CORS tidak di-cache (`max_age: 0`) | Semua request API jadi 2x | 1 baris |
| 2 | Laravel tidak di-cache (`config/route/event/view`) | ~10-20 ms CPU per request [INFERENSI] | kecil |
| 3 | Apache prefork tanpa batas worker, tanpa limit memori, Postgres `max_connections` bawaan 100 | OOM atau `too many clients` saat burst | kecil |
| 4 | Dashboard siswa: 40-60 query per request di BE, 8-9 request di FE | Beban terbesar di jalur paling sering dibuka | sedang |
| 5 | Leaderboard: query CTE berat dihitung ulang tiap request, tanpa cache | CPU DB naik seiring data | kecil |
| 6 | Submit memanggil analytics-api sinkron (timeout 5 dtk x 3 percobaan) | Worker Apache habis bila analytics lambat | sedang |
| 7 | `/auth/me` gagal (bukan hanya 401) menghapus token | Logout massal saat server lambat | kecil |
| 8 | Polling nilai memakai `POST submit` tiap 5 dtk tanpa backoff | Badai request saat penilaian bermasalah | sedang |
| 9 | Autosave 1 PUT per jawaban (~8 query), tanpa retry/flush | Beban + jawaban bisa hilang | sedang |
| 10 | Tanpa `throttle:api` untuk user login, register tanpa throttle | Tidak ada rem bila FE bermasalah | kecil |

Perkiraan kapasitas saat ini [INFERENSI, asumsi VPS 2 vCPU / 4 GB]: sekitar 300-400
siswa aktif bersamaan; sekitar 600-800 setelah temuan 1 dan 2. Untuk 1000+ siswa
serentak, minimal temuan 1-6 harus selesai. **Pastikan dulu ukuran VPS** karena semua
estimasi bergantung padanya.

## A. Frontend

Hitungan request per alur (dari kode; T = jumlah tingkat terbuka):

| Alur | Request |
|---|---|
| Login | 1 (`POST /auth/login`) |
| Dashboard siswa, sudah pretest | 7+T (8-9) |
| Dashboard siswa, belum pretest | 3 |
| Reload (F5) | +1 `/auth/me` lalu dashboard |
| Pretest 20 soal | ~33-38 (inti hanya 22: mulai, 20 PUT, submit) |
| Latihan N soal | N+2 |
| Tiap klik "Tandai sebagai Selesai" pada submateri | 1 PUT + 7-10 baca ulang dashboard |

Sudah benar: `/auth/me` hanya sekali per page-load dengan dedup (`router/index.js:245`,
`stores/auth.js:48`), tidak ada interceptor retry (`lib/api.js:38-63`), tidak ada
polling saat idle, pencarian materi/admin dilakukan di klien.

### FE-1 (Tinggi) Dashboard fan-out besar dan sering dipaksa muat ulang
- `layouts/AppLayout.vue:56` memanggil `progress.fetchDashboard()` di setiap masuk
  halaman siswa, termasuk saat langsung ke halaman ujian.
- `stores/progress.js:42-89`: waterfall `/dashboard` -> `/tingkat` -> `/materi` x T ->
  `/riwayat` -> `/pretest/{id}`. Cache hanya 60 detik (`SEGAR_MS`, baris 15).
- `/dashboard` sudah memuat daftar tingkat, tetapi FE tetap memanggil `/tingkat` untuk
  field yang sama.
- `force: true` memotong cache di `MateriView.vue:268` (tiap submateri selesai),
  `SimulasiView.vue:316`, `PretestView.vue:70,200`, `UjianSimulasiView.vue:143`,
  `DashboardView.vue:37`.
- Contoh: materi dengan 5 submateri = sekitar 40 request hanya untuk menandai selesai.
- Saran: hapus `fetchDashboard` dari `AppLayout`; setelah PUT submateri cukup patch
  state lokal; satukan agregat (materi selesai, rata-rata simulasi, 5 riwayat terbaru)
  ke respons `/dashboard`; ganti `force` dengan invalidasi berbasis event.

### FE-2 (Tinggi) `/auth/me` gagal menghapus token
- `stores/auth.js:64-69`: `catch { user.value = null; saveToken(null) }` untuk error
  apa pun (timeout, 502, network), bukan hanya 401. Guard menunggu request ini sampai
  15 detik.
- Saat server lambat siswa menekan F5, token terhapus, lalu login ulang. Lonjakan
  `POST /auth/login` ikut kena `throttle:login` (5/menit per identitas+IP,
  `AppServiceProvider.php:34-37`); banyak siswa satu jaringan kampus bisa saling
  menghalangi.
- Saran: hapus token hanya untuk 401; selain itu tampilkan layar "coba lagi".

### FE-3 (Sedang) Polling nilai memakai `POST submit` tiap 5 detik
- `PretestView.vue:65`, `LatihanView.vue:161-168`, `UjianSimulasiView.vue:130-138`,
  `HasilSimulasiView.vue:69-75`, `PemetaanKompetensiView.vue:60-76`. `cekHasil` memanggil
  `kumpulkan()` = `POST .../submit` (`stores/pretest.js:188-191`), yang di server
  membuka transaksi dengan lock, memanggil analytics, dan bila gagal mengirim job baru.
- Hanya aktif saat status `MENILAI`, yaitu saat penilaian pertama gagal. Jadi ini
  **memperparah keadaan buruk**, bukan beban normal. Tapi justru muncul saat analytics
  lambat: 1000 siswa menunggu = ~200 POST/detik.
- `setInterval(async ...)` tanpa penjaga in-flight; timeout axios 15 dtk vs interval 5
  dtk membuat sampai 3 POST menumpuk per klien. Tanpa backoff, jitter, atau batas.
- Saran: endpoint GET read-only untuk status; backoff 5 -> 10 -> 20 -> 30 dtk dengan
  jitter dan batas percobaan; hentikan saat tab tersembunyi.

### FE-4 (Sedang) Auto-submit simulasi bisa mengulang tiap detik
- `UjianSimulasiView.vue:94,201`: `hitungSisa` (tiap 1 dtk) memanggil
  `kumpulkan({paksa:true})` selama `sisa===0`. Bila gagal, status kembali
  `MENGERJAKAN` (`stores/simulasi.js:174-178`) dan tick berikutnya mengulang, plus toast
  error tiap kali. Siswa yang mulai bersamaan berakhir bersamaan [INFERENSI].
- Saran: sekali-tembak dengan flag (seperti `sudahKumpulOtomatis` di pretest), retry
  backoff, dan jitter 0-N detik.

### FE-5 (Sedang) Autosave: 1 PUT per jawaban, tanpa retry atau flush
- Debounce 800 ms di `PretestView.vue:209-219`, `LatihanView.vue:107-117`,
  `UjianSimulasiView.vue:97-112` (tiga salinan kode). Gagal hanya mengisi
  `simpanError` (`stores/pretest.js:151-160`); event `online` hanya mengganti banner.
- Jawaban yang diubah <800 ms sebelum submit atau unmount hilang (`onBeforeUnmount`
  hanya `clearTimeout`).
- Saran: satu composable `useAutosave` dengan antrean jawaban "kotor", flush saat pindah
  soal/submit/unmount, retry backoff dan kirim ulang saat `online`; opsional endpoint
  batch tiap 3-5 detik.

### FE-6 (Sedang) Duplikasi dan N+1 antar halaman
- `GET /materi` dipanggil langsung lewat `belajarService` dan melewati cache
  `stores/materi.js`: `progress.js:117`, `MateriView.vue:162`, `RiwayatView.vue:222-228`
  (termasuk tingkat terkunci), `PemetaanKompetensiView.vue:250`.
- `GET /pretest/{id}` berulang: `RiwayatView.vue:215-251` satu request per item riwayat
  (`per_page:100`, bisa 100 request), `PemetaanKompetensiView.vue:259-269` sampai 4.
- `/tingkat` dipaksa melewati cache 5 menit di `RiwayatView.vue:210`,
  `SimulasiView.vue:318`, `UjianSimulasiView.vue:144`.
- Saran: semua lewat store ber-cache; cache `/pretest/{id}`; batasi N+1 riwayat (5 item
  atau muat saat baris dibuka); minta BE menyertakan ringkasan pemetaan di `/riwayat`.

### FE-7 (Rendah)
- `console.log('Sisa waktu', ...)` tiap detik di `PretestView.vue:243-247` (bundle
  produksi).
- `stores/materi.js:22` mengembalikan `inflight` tanpa cek `tingkatId`: ganti tingkat
  cepat (`ProgressView.vue:80-89`) bisa menampilkan daftar yang salah.
- Detail materi tanpa cache (`stores/materi.js:53-65`); pertimbangkan ETag/304.
- Timer pretest hanya di klien (`lib/pretest.js:8-9`, `PretestView.vue:279`): reload
  mengembalikan waktu penuh. Ini soal keadilan ujian, bukan beban. Simulasi sudah memakai
  `batas_pada` dari server; pretest sebaiknya meniru.

## B. Backend

Perkiraan query per request (dari kode, `vendor/` tidak ada sehingga perilaku internal
Sanctum/Spatie [INFERENSI]):

| Endpoint | Query |
|---|---|
| Baseline tiap endpoint terautentikasi | 3-4 |
| `GET /auth/me` | ~6 |
| `PUT quiz-pengerjaan/{id}/jawaban` (autosave) | ~8 |
| `GET /dashboard` | 40-60 (2-3 tingkat) |
| `GET /leaderboard` | 4 (2 CTE berat) |
| `GET /leaderboard/me` | ~5 (2 CTE berat) |
| `GET /materi`, `/materi/{id}` | ~15 |

### BE-1 (Tinggi) Dashboard 40-60 query, syarat simulasi dihitung dua kali
- `DashboardService.php:44-47` memanggil `putaranService->status()` lalu
  `syaratService->periksa()` per tingkat. `PutaranService.php:50` (`tentukanTahap`)
  sudah memanggil `periksa()` yang sama (`:104-120`).
- `SyaratSimulasiService.php:35-47` ~18-20 query per tingkat;
  `TingkatSeleksiRepository::semuaTerurut()` (`:20`) tidak dimemo.
- `status()` juga dipanggil ulang di `/materi` (`BelajarService.php:213-228`),
  `/tingkat`, dan mulai latihan.
- Saran: memo `periksa()` dan `semuaTerurut()` per request (scoped, seperti
  `AturanService`, `RepositoryServiceProvider.php:136`); teruskan `$syarat` hasil
  `status()` ke dashboard; cache `tingkat_seleksi` dan `aturan_pemetaan` (nyaris
  statis); cache dashboard per user 10-30 detik, invalidasi saat submit.

### BE-2 (Tinggi) Tidak ada cache aplikasi; leaderboard menghitung CTE berat tiap request
- `LeaderboardService.php:30-55, 128-130, 168-215`. Grep `Cache::` di `app/`: nol.
- `top()` = `tingkatId()` dengan `LOWER(nama_tingkat) LIKE`, CTE `ranked`
  (`DISTINCT ON (user_id, quiz_id)` atas seluruh `quiz_pengerjaan` per tingkat, join
  `users`, `NOT EXISTS` ke `model_has_roles`, `RANK()`), `totalLatihan()`, dan CTE lagi di
  `hitungPeserta()`. `posisiSaya()` (`:57-105`) mengulangnya. Halaman leaderboard yang
  memanggil `index` + `me` menjalankan CTE penuh ~3 kali.
- Leaderboard kini terbuka untuk semua akun yang login (termasuk anggota), jadi
  jumlah pemanggil naik.
- Saran: `Cache::remember` 30-60 detik untuk `top()` per tingkat (termasuk
  `total_peserta`, `totalLatihan`); cache `tingkatId` lebih lama; ganti `hitungPeserta`
  dengan `COUNT(*) OVER()`; cache `me` per user TTL pendek. Alternatif jangka panjang:
  tabel skor yang diperbarui saat submit.

### BE-3 (Tinggi) Submit memanggil analytics-api sinkron
- `LatihanService.php:149-165` (sama di `PretestService.php:201`,
  `SimulasiService.php:284`). `PerhitunganClient.php:33-34, 141-145` (+
  `config/services.php:72-73`): timeout 5 dtk, retry 2 (3 percobaan) = hingga ~15 dtk per
  request. Panggilan memang di luar transaksi DB (benar).
- Bila analytics lambat atau mati saat ujian serentak, semua worker Apache habis dan
  seluruh API (autosave, `/me`) ikut macet.
- Saran: timeout 2-3 dtk dan retry 1; atau jadikan penilaian asinkron (dispatch
  `NilaiUlangJob` yang sudah ada, FE polling status lewat GET); circuit breaker.

### BE-4 (Sedang) Rate limit hampir tidak ada
- `routes/api.php:41-42`: hanya `login` dan `google/exchange` yang di-throttle.
  `auth/register` tanpa throttle padahal bcrypt (`BCRYPT_ROUNDS=12`, `.env.example:18`)
  mahal di CPU. Grup `auth:sanctum` tanpa `throttle:api`.
- Saran: `throttle:api` per user (mis. 120/menit), throttle register per IP, throttle
  khusus submit.

### BE-5 (Sedang) Query tambahan di setiap request
- Sanctum v4.3.3 mencari token + user + UPDATE `last_used_at` tiap request
  [INFERENSI, vendor tidak ada].
- `TolakAnggota.php:13` (middleware fitur anggota ini) memicu 1 query `roles` pada
  request di grup `bukan.anggota`. `LeaderboardController.php` dan `AuthController.php:61`
  juga memuat ulang `roles`. Saran: satu titik `loadMissing('roles')` per request atau
  cache role per user beberapa menit.
- `AuthController.php:61` `loadCount('diundang')` tiap `/auth/me`: muat hanya untuk
  anggota atau cache.

### BE-6 (Sedang) Index database kurang di jalur panas
Sudah ada: `(user_id, quiz_id)`, `(user_id, tingkat_id)` pada pretest,
`(user_id, pretest_id)` pada `hasil_simulasi`. Yang kurang:
- `quiz_pengerjaan(quiz_id)` / `(quiz_id, user_id)` untuk leaderboard (sebaiknya
  partial `WHERE nilai IS NOT NULL`).
- `pretest(tingkat_id, user_id)` untuk CTE `lolos`.
- `hasil_simulasi(simulasi_id)` (FK tanpa index).
Postgres tidak membuat index FK otomatis. Verifikasi dengan `EXPLAIN ANALYZE` pada data
nyata sebelum menambah.

### BE-7 (Sedang) Update per baris di dalam transaksi berkunci
- `LatihanService.php:213-225`: `foreach` + `save()` = N UPDATE di dalam
  `DB::transaction` dengan `lockForUpdate`. `RekomendasiMateriRepository::buatBanyak`
  (`:20-34`) memakai `updateOrCreate` per baris (2 query per materi saat submit
  pretest). Pretest/Simulasi: pola serupa [INFERENSI].
- Saran: satu `upsert()` atau `UPDATE ... FROM (VALUES ...)`.

### BE-8 (Rendah)
- Respons tanpa batas: `Admin/SiswaController.php:23` `per_page` tanpa batas atas;
  `DashboardAdminService.php:17-49` banyak `COUNT`/`AVG` penuh tabel tiap request;
  `AnggotaService.php:119-129` hitung ulang agregat referal tiap `/anggota/ringkasan`
  (naik seiring jumlah anggota; 32 anggota tidak masalah).
- `/materi` meng-hydrate `isi_materi` (teks panjang) yang tidak dikirim
  (`MateriRepository::untukTingkat:20-26`, `BelajarService.php:430`).
- `pecahSubmateri` (regex PHP) tiap `/materi/{id}` (`BelajarService.php:374-385`):
  cache per `materi.id + updated_at`.
- `SoalRepository::kandidat` memuat model penuh (`:23-35`), cukup `id,materi_id,level`;
  `soalIdsTerpakai` memakai `whereHas` bersarang 3 tingkat.

### Yang sudah baik
- Submit: `lockForUpdate` di transaksi pendek, analytics di luar transaksi.
- Idempotensi lewat unique partial index (`quiz_pengerjaan_berjalan_unique`,
  `pretest_berjalan_unique`, `hasil_simulasi_berjalan_unique`).
- Autosave memakai UPDATE per baris tanpa transaksi (murah, tidak saling berkunci).
- `AturanService` dimemo per request; riwayat sudah `paginate(15)`.
- Tidak ada email atau impor berat di jalur request.

## C. Infra dan deploy

Topologi: backend `php:8.4-apache` (mod_php, prefork bawaan), Postgres 16 tunggal untuk
data + queue + session, frontend `nginx:alpine`, analytics-api satu proses uvicorn,
tanpa Redis. `CACHE_STORE=file`, `QUEUE_CONNECTION=database`
(`docker-compose.prod.yml:37-38`). Konfigurasi reverse proxy host tidak ada di repo
(`docs/CI-CD-DEPLOY-VPS.md:25-33`); trafik diduga lewat Cloudflare
(`bootstrap/app.php:27-31`), jadi gzip/rate limit di edge tidak bisa diverifikasi.

### INF-1 (Tinggi) Preflight CORS tidak di-cache
- `config/cors.php:35`: `'max_age' => 0`. FE dan API beda origin dan memakai header
  `Authorization`, jadi browser mengirim OPTIONS sebelum hampir tiap GET/PUT/POST.
  Tiap OPTIONS mem-boot Laravel dan memakai satu worker Apache.
- Saran: `'max_age' => 7200` (batas Chrome). Lebih baik lagi: FE dan API same-origin
  lewat proxy `/api` sehingga preflight hilang.

### INF-2 (Tinggi) Apache prefork tanpa tuning; Postgres bawaan
- `docker/apache-production.conf` dan `Dockerfile.production` tidak menyetel MPM.
  Bawaan Debian: `MaxRequestWorkers` 150 [INFERENSI, default image]. `memory_limit` 128M
  bawaan (`php-production.ini` tidak menyetel).
- Compose prod tanpa `mem_limit`/`cpus` untuk service mana pun (bandingkan
  `data-analytics/docker-compose.yml:26-30` yang punya 1 CPU / 1G). Postgres tanpa
  `command:`: `max_connections=100`, `shared_buffers=128MB` bawaan. Laravel tidak memakai
  koneksi persisten, jadi 1 request = 1 koneksi PG.
- Skenario 300+ siswa serentak: PG kena `too many clients` (>100) -> HTTP 500; atau 150
  worker x ~30-50 MB [INFERENSI] = 4,5-7,5 GB -> OOM di VPS 4 GB, bisa mematikan Postgres.
- Saran: `MaxRequestWorkers` ~40-60 sesuai RAM, `MaxConnectionsPerChild 500`,
  `KeepAliveTimeout 2`; `mem_limit` per service; Postgres `max_connections` dan
  `shared_buffers` (~25% RAM); PgBouncer bila worker dinaikkan. Jangka menengah: php-fpm
  + nginx atau Octane.

### INF-3 (Tinggi) Config/route tidak di-cache
- `Dockerfile.production:40-46` hanya `package:discover` dan `storage:link`. Opcache
  sudah aktif dengan `validate_timestamps=0` (benar), tapi parsing config dan ratusan
  route tetap per request (~10-20 ms [INFERENSI]).
- Saran: `php artisan optimize` (config, route, event, view). Karena env diinjeksi saat
  runtime, jalankan di entrypoint/CMD saat start, bukan saat build.

### INF-4 (Sedang)
- **Deploy membangun image di host produksi** (`scripts/deploy-vps.sh:56-61`,
  runner self-hosted) dan `up -d` merecreate backend (jeda 502). Jangan deploy saat
  ujian; idealnya build di CI lalu `docker pull`.
- **`index.html` tanpa Cache-Control** (`nginx.production.conf:37-39`): HTML lama
  ter-cache bisa menunjuk aset ber-hash yang sudah tidak ada. Tambah `no-cache` untuk
  `/index.html`. Tidak ada `gzip on` di nginx frontend (bila tidak lewat Cloudflare).
- **bcrypt 12 ronde** (`.env.example:18`): ~150-300 ms CPU per login [INFERENSI].
  1000 login dalam 1 menit = ~2 menit CPU jenuh pada 2 vCPU. Jadwalkan login bertahap
  atau turunkan ke 10 ronde bila risikonya diterima.
- **analytics-api satu proses** (`data-analytics/Dockerfile:26`): `--workers 2` dan batas
  CPU.

### INF-5 (Rendah)
- Log: `LOG_LEVEL` default `debug`, `LOG_STACK=single` tanpa rotasi
  (`config/logging.php:57,64`); Docker tanpa `max-size`/`max-file`.
- Aset upload (`/storage`) disajikan Apache/PHP tanpa header cache (`mod_expires` tidak
  aktif).
- JSON API tanpa kompresi dari origin (`deflate` bawaan tidak mencakup
  `application/json` [INFERENSI]).
- `queue-worker`: `--timeout=90` sama dengan `retry_after` 90 (`config/queue.php:43`);
  `NilaiUlangJob` idempoten sehingga aman. Tambah `--max-time=3600` dan healthcheck.
  `frontend` dan `queue-worker` tanpa healthcheck.

## D. Apakah perlu Redis?

**Belum wajib, dan bukan perbaikan pertama.** Penyebab beban terbesar ada di kode dan
konfigurasi (query dashboard, tidak ada cache, preflight CORS, tanpa batas worker),
bukan di pilihan driver. Redis tidak akan menolong sebelum itu diperbaiki.

Urutan yang disarankan:
1. Perbaiki kode/konfigurasi (bagian E, tahap 1-2). Gratis, dampak terbesar.
2. Setelah `Cache::remember` ditambahkan, `CACHE_STORE=file` pada satu container backend
   masih memadai. Cache file tidak dibagi antar container.
3. **Tambah Redis bila salah satu terjadi:**
   - backend akan di-scale ke lebih dari satu container (cache + rate limiter harus
     dibagi, kalau tidak limit jadi per replika);
   - tabel `jobs` di Postgres mulai jadi bottleneck (polling worker menambah beban DB
     yang sudah ditekan);
   - leaderboard di-cache dengan TTL pendek dan banyak kunci per user.
   Satu container `redis:7-alpine` (~50 MB RAM) untuk `CACHE_STORE`, `QUEUE_CONNECTION`,
   dan rate limiter sudah cukup untuk skala ini. Session tidak relevan (API memakai
   bearer token).
4. Yang lebih berguna daripada Redis di VPS tunggal: PgBouncer atau batas worker yang
   sejalan dengan `max_connections`, dan menaikkan RAM/CPU VPS.

## E. Rencana perbaikan berurutan

**Tahap 1: sebelum uji coba (konfigurasi, usaha kecil)**
1. `cors.max_age` 7200 (INF-1).
2. `php artisan optimize` di entrypoint/CMD (INF-3).
3. `MaxRequestWorkers`, `mem_limit`, Postgres `max_connections`/`shared_buffers` sesuai
   ukuran VPS (INF-2).
4. Timeout analytics 2-3 dtk dan retry 1 (BE-3).
5. FE: hapus `fetchDashboard` dari `AppLayout`; hapus token hanya pada 401 (FE-1, FE-2).
6. `LOG_LEVEL=warning`, batas log Docker; `no-cache` untuk `index.html` (INF-5, INF-4).

**Tahap 2: kode (sedang)**
7. Memo `periksa()`/`semuaTerurut()`, cache `tingkat_seleksi`, hapus panggilan ganda di
   dashboard (BE-1).
8. `Cache::remember` untuk leaderboard (BE-2).
9. Index baru (BE-6), diverifikasi dengan `EXPLAIN ANALYZE`.
10. Polling lewat GET dengan backoff; auto-submit sekali dengan jitter (FE-3, FE-4).
11. `throttle:api` dan throttle register (BE-4).
12. Patch lokal setelah submateri selesai; satukan agregat ke `/dashboard` (FE-1).

**Tahap 3: bila perlu skala lebih besar**
13. `useAutosave` dengan flush/retry/batch (FE-5).
14. Penilaian submit asinkron penuh (BE-3).
15. Redis, PgBouncer, php-fpm/Octane (bagian D, INF-2).

## F. Cara membuktikan sebelum dan sesudah

Audit ini perkiraan. Sebelum uji coba besar, ukur:
- **Uji beban** (k6 atau Locust) ke staging dengan alur: login -> dashboard -> mulai
  latihan -> 20 autosave -> submit, naikkan bertahap 50 -> 200 -> 500 pengguna virtual.
  Perhatikan p95, error 5xx, koneksi PG, dan memori container.
- Hitung query nyata per endpoint dengan `DB::listen` atau Telescope/Debugbar di
  lokal, bandingkan dengan tabel bagian B.
- `docker stats` dan `SELECT count(*) FROM pg_stat_activity` selama uji.
- Uji skenario gagal: matikan `analytics-api` saat submit untuk melihat perilaku
  timeout (BE-3, FE-3).

## Status perbaikan (diperbarui)

Dikerjakan dan diukur:

| Butir | Perubahan | Bukti |
|---|---|---|
| INF-1 | `cors.max_age` 7200 | baris OPTIONS di Network hilang; sesi FE 150 → 49 baris |
| INF-3 | `php artisan config:cache/event:cache/view:cache/route:cache` di entrypoint container (bukan build) | log container: 4 cache berhasil, `/up` 200 |
| INF-2 | MPM prefork 48 worker, `MaxConnectionsPerChild` 500, KeepAlive 2 dtk, `memory_limit` 256M | `apache2 -t` OK, `memory_limit=256M`, `MaxRequestWorkers 48` |
| INF-2 | compose: `mem_limit`/`cpus` per service, Postgres `max_connections=120`/`shared_buffers=256MB`/`work_mem=8MB`, log Docker dibatasi 10m×5, healthcheck frontend | `docker compose config` valid |
| INF-4 | gzip aset + `Cache-Control: no-cache` untuk `index.html` | image FE: index `no-cache`, aset `immutable` + `Content-Encoding: gzip`, SPA fallback 200 |
| INF-4 | analytics timeout 3 dtk, retry 1 | `config/services.php`, `.env.example` |
| BE-1 | syarat simulasi tidak dihitung dua kali di `/dashboard` | query `/api/dashboard` **21 → 15** (diukur dengan `DB::listen`, fixture 1 tingkat + 1 materi wajib) |
| BE-2 | leaderboard di-cache 30 dtk per tingkat, satu CTE untuk daftar + posisi | dua kali buka `/leaderboard` + `/leaderboard/me`: CTE **6 → 0**, query **14 → 6**; permintaan pertama 3 → 1 CTE |
| BE-4 | `throttle:api` 180/menit per user, registrasi 5/menit per IP | `ThrottleApiTest` |
| BE-5 | `loadMissing('roles')` di middleware anggota | suite lulus |
| BE-6 | index `quiz_pengerjaan(quiz_id,user_id)`, `pretest(tingkat_id,user_id)`, `hasil_simulasi(simulasi_id)`, `hasil_simulasi(pretest_id)` | migrasi `2026_11_08_000002` terpasang, index muncul di `pg_indexes` |
| FE-2 | `/auth/me` dicoba ulang 300 ms/900 ms untuk 5xx/timeout sebelum token dihapus | tes baru di `auth.spec.js` |
| FE lain | `console.log` timer dihapus; `.dockerignore` mencegah `.env` dan manifest dev ikut ke image | build image produksi berhasil dari working tree kotor |

Pengujian: backend 515 lulus (7 gagal = tes view Breeze `Vite manifest not found`, baseline lama), frontend 257 lulus.

Langkah deploy dan verifikasi di VPS: [PANDUAN-CEK-SETELAH-DEPLOY-OPTIMASI.md](PANDUAN-CEK-SETELAH-DEPLOY-OPTIMASI.md).

Belum dikerjakan: agregat dashboard dalam satu respons (FE), ringkasan benar/total di `/riwayat`, polling nilai lewat GET + backoff, autosave batch/retry, submit asinkron penuh, Redis/PgBouncer.
