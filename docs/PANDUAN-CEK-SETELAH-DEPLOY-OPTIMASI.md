# Panduan: Cek Setelah Deploy Optimasi Server (PR #53)

Dokumen ini berlaku setelah perubahan kapasitas, cache bootstrap, dan index di
`feat`/`perf/server-capacity` masuk `monorepo`. Latar belakang dan angka hasil ukur ada
di [AUDIT-BEBAN-SERVER-DAN-REQUEST.md](AUDIT-BEBAN-SERVER-DAN-REQUEST.md). Cara deploy
umum ada di [CI-CD-DEPLOY-VPS.md](CI-CD-DEPLOY-VPS.md) dan
[PANDUAN-DEPLOY-MONOREPO-SATU-SERVER.md](PANDUAN-DEPLOY-MONOREPO-SATU-SERVER.md).

Tidak ada langkah manual wajib di luar yang tertulis di sini: `scripts/deploy-vps.sh`
sudah membangun image, membuat ulang service, menjalankan migrasi, dan menyalakan
`queue-worker`. Empat hal di bawah tetap perlu diperiksa.

## 1. Sebelum deploy: periksa `.env.production`

Nilai lama di `.env.production` menang atas default baru di `config/`, jadi pastikan:

```bash
cd /opt/osn-monorepo
grep -E '^(PERHITUNGAN_TIMEOUT|PERHITUNGAN_RETRY|LOG_LEVEL)=' .env.production
```

| Variabel | Nilai yang diinginkan | Catatan |
|---|---|---|
| `PERHITUNGAN_TIMEOUT` | `3` | default baru 3 detik (dulu 5) |
| `PERHITUNGAN_RETRY` | `1` | default baru 1 (dulu 2) |
| `LOG_LEVEL` | `warning` | default compose `warning`; jangan `debug` di produksi |

Tidak ada variabel baru yang wajib diisi.

## 2. Sebelum deploy: cocokkan dengan RAM VPS

`MaxRequestWorkers 48` dan `mem_limit: 2g` untuk `backend` mengasumsikan RAM backend
±2 GB (48 worker × ±40 MB). Ukuran VPS tidak tercatat di repo, jadi periksa:

```bash
free -h
```

Bila RAM lebih kecil, turunkan `MaxRequestWorkers` di
`apps/Osn-Readiness-Web/docker/mpm_prefork.conf` dan `mem_limit` service `backend` di
`docker-compose.prod.yml`, lalu deploy. Jumlah `mem_limit` semua service adalah batas
atas, bukan reservasi; yang penting `backend` tidak melebihi RAM yang tersedia.

## 3. Deploy

```bash
cd /opt/osn-monorepo
git fetch && git checkout monorepo && git pull
bash scripts/deploy-vps.sh
```

Yang dilakukan skrip: `build` (backend, frontend, analytics) → `up -d app-db
analytics-api backend frontend` → `migrate --force` (termasuk migrasi index baru) →
`--profile worker up -d queue-worker`.

Dua konsekuensi yang perlu diketahui:

- **`app-db` dibuat ulang** karena `command` Postgres berubah (max_connections,
  shared_buffers, work_mem). Data aman di volume `app_pgdata`; downtime beberapa detik.
- **`backend` dibuat ulang** sehingga ada jeda 502 singkat. Jangan deploy saat ujian.

## 4. Verifikasi

```bash
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"

# Bootstrap cache dijalankan entrypoint saat start: harus 4 baris.
$COMPOSE logs backend | grep -c "cached successfully"

# Konfigurasi PHP dan Postgres.
$COMPOSE exec -T backend php -r 'echo ini_get("memory_limit"), "\n";'        # 256M
$COMPOSE exec -T app-db psql -U "$APP_DB_USERNAME" -d "$APP_DB_DATABASE" -Atc 'show max_connections'  # 120

# Empat index baru (harus keluar 4 baris).
$COMPOSE exec -T app-db psql -U "$APP_DB_USERNAME" -d "$APP_DB_DATABASE" -Atc "
select indexname from pg_indexes where indexname in (
  'quiz_pengerjaan_quiz_id_user_id_index',
  'pretest_tingkat_id_user_id_index',
  'hasil_simulasi_simulasi_id_index',
  'hasil_simulasi_pretest_id_index')"

# Preflight CORS di-cache browser: harus 7200.
curl -s -D - -o /dev/null -X OPTIONS \
  -H 'Origin: https://DOMAIN-FE' -H 'Access-Control-Request-Method: GET' \
  https://DOMAIN-API/api/dashboard | grep -i access-control-max-age

# Header frontend.
curl -sI https://DOMAIN-FE/index.html | grep -i cache-control                 # no-cache
curl -sI -H 'Accept-Encoding: gzip' https://DOMAIN-FE/ASSET.js | grep -iE 'content-encoding|cache-control'

# Health check.
curl -s -o /dev/null -w '%{http_code}\n' https://DOMAIN-API/up               # 200
```

Uji manual setelah itu: login siswa → dashboard → leaderboard. Di tab Network seharusnya
tidak ada lagi pasangan `OPTIONS` + `GET` untuk setiap request, dan klik ulang halaman
yang sama tidak memicu request baru.

## 5. Catatan operasional

- **Ubah `.env.production` berarti restart**: `config:cache` dijalankan entrypoint saat
  start, jadi jalankan `$COMPOSE up -d backend queue-worker` setelah mengubah env.
  Untuk men-debug dengan konfigurasi segar: `$COMPOSE exec -T backend php artisan
  optimize:clear`.
- **Migrasi index memakai `CREATE INDEX` biasa**, bukan `CONCURRENTLY`, sehingga menahan
  tulis singkat pada `quiz_pengerjaan`, `pretest`, dan `hasil_simulasi`. Bila tabel sudah
  sangat besar, jalankan deploy di luar jam ujian.
- **`queue-worker` memakai image yang sama dengan `backend`.** Kalau `backend` di-update
  tanpa worker, worker masih menjalankan kode lama. `deploy-vps.sh` sudah menyalakan
  keduanya.
- **`AnggotaSeeder` tidak jalan otomatis.** Data anggota tidak berubah karena deploy ini.
- **Redis belum dipakai dan belum perlu** pada skala satu container; lihat bagian D di
  dokumen audit.

## 6. Rollback

```bash
cd /opt/osn-monorepo
git checkout <commit-sebelum-deploy>
bash scripts/deploy-vps.sh
$COMPOSE exec -T backend php artisan migrate:rollback --step=1   # bila index perlu dicabut
```

Index tambahan aman dicabut (tidak ada data yang bergantung padanya). Cache bootstrap
dibuat ulang otomatis saat container start.

## 7. Yang sudah dibuktikan sebelum dokumen ini ditulis

Diuji pada image produksi yang dibangun dari branch ini, dengan Postgres baru:

- entrypoint mencetak 4 cache berhasil (config, events, blade, routes);
- `POST /api/auth/login` → 200, `GET /api/auth/me` → 200, `GET /api/dashboard` → 200
  dengan data dari database (membuktikan `config:cache` tidak memutus env/DB);
- preflight membalas `Access-Control-Max-Age: 7200`;
- `memory_limit=256M`, `MaxRequestWorkers 48`, `apache2 -t` OK;
- image frontend: `index.html` `no-cache`, aset `immutable` + `Content-Encoding: gzip`,
  SPA fallback 200, `/healthz` 200;
- uji backend 515 lulus (7 gagal = tes view Breeze `Vite manifest not found`, baseline
  lama), frontend 257 lulus.
