# Panduan Staging SIAP OSN

Panduan ini untuk menyiapkan dan menjalankan environment staging. Untuk produksi,
lihat [CI-CD-DEPLOY-VPS.md](CI-CD-DEPLOY-VPS.md).

## 1. Gambaran singkat

| | Produksi | Staging |
| --- | --- | --- |
| Branch | `monorepo` | `1-staging` |
| Cara deploy | otomatis saat push ke `monorepo` | manual, lewat Run workflow |
| Job deploy | `deploy` | `deploy-staging` |
| File env | `.env.production` | `.env.staging` |
| Project Compose | default (nama direktori) | `osn-staging` |
| Port host | 8081 (frontend), 8080 (backend) | 8091 (frontend), 8090 (backend) |
| Hostname | `mrcln0305.xyz`, `api.mrcln0305.xyz` | `stag.mrcln0305.xyz`, `stag-api.mrcln0305.xyz` |
| Environment GitHub | `production` | `staging` |

Push ke branch `1-staging` **tidak** menjalankan action. Deploy staging hanya jalan
saat dijalankan manual.

## 2. Persiapan (DevOps, sekali saja)

### 2.1 DNS dan reverse proxy

Hostname staging diatur di tiga tempat: DNS, reverse proxy di VPS, dan file
`.env.staging`. Langkah DNS dan reverse proxy:

1. **DNS** (di tempat domain `mrcln0305.xyz` dikelola):
   - Record `stag` → IP VPS (A record), atau CNAME ke host produksi.
   - Record `stag-api` → IP VPS (A record), atau CNAME ke host produksi.
   - Jika DNS memakai Cloudflare, sesuaikan status proxy dengan kebutuhan TLS.
2. **Reverse proxy** di VPS (contoh nginx; sesuaikan dengan proxy yang dipakai):
   - `stag.mrcln0305.xyz` → `http://127.0.0.1:8091`
   - `stag-api.mrcln0305.xyz` → `http://127.0.0.1:8090`, dengan header `Host`,
     `X-Forwarded-Proto`, dan `X-Forwarded-For` diteruskan.
3. **TLS**: sertifikat untuk kedua hostname, misalnya dengan certbot.

### 2.2 Di server: direktori dan file env

1. Siapkan direktori checkout repo di VPS. Boleh sama dengan direktori produksi.
2. Pastikan runner `self-hosted` bisa menjalankan `git fetch` ke repo, menulis di
   direktori itu, dan menjalankan `docker`. Biasanya user runner masuk grup `docker`.
3. Pindah ke branch `1-staging` dan buat file env:

   ```bash
   cd <path checkout di VPS>
   git fetch origin 1-staging
   git checkout 1-staging
   cp .env.production.example .env.staging
   chmod 600 .env.staging
   ```

4. Isi `.env.staging`. Semua placeholder harus diganti:

   | Variable | Isi |
   | --- | --- |
   | `APP_URL` | `https://stag-api.mrcln0305.xyz` |
   | `FRONTEND_URL` | `https://stag.mrcln0305.xyz` |
   | `VITE_API_BASE_URL` | `https://stag-api.mrcln0305.xyz/api` |
   | `GOOGLE_REDIRECT_URI` | `https://stag-api.mrcln0305.xyz/auth/google/callback` |
   | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | client Google OAuth milik staging |
   | `APP_KEY` | `base64:` + hasil `openssl rand -base64 32` |
   | `APP_DB_PASSWORD` | hasil `openssl rand -hex 32` |
   | `INTERNAL_API_TOKEN` | hasil `openssl rand -hex 32`, **berbeda** dari `APP_DB_PASSWORD` |
   | `APP_DB_DATABASE` | `osn_readiness_staging` |
   | `APP_DB_USERNAME` | `osn_app` (atau sesuai produksi) |
   | `FRONTEND_HOST_PORT` | `8091` |
   | `BACKEND_HOST_PORT` | `8090` |

   Jangan menyalin `APP_KEY`, password, atau token dari produksi.

   `VITE_API_BASE_URL` di-build ke dalam frontend. Jika nilainya berubah, jalankan
   deploy ulang agar frontend ikut ter-build.

5. Daftarkan `https://stag-api.mrcln0305.xyz/auth/google/callback` sebagai redirect URI
   di Google OAuth client staging.

### 2.3 Di GitHub

1. Environment `staging` sudah ada. Tidak perlu dibuat ulang.
2. Buka **Settings → Secrets and variables → Actions → Variables** (level repository),
   lalu tambahkan:
   - `DEPLOY_STAGING_ENABLED` = `true`
   - `STAGING_DEPLOY_DIR` = path checkout di VPS, misalnya `/opt/osn-staging`

   Pakai level repository. Gate di job `deploy-staging` dibaca sebelum environment
   dipilih, jadi variable level environment belum tentu terbaca.
3. Secret `VPS_*` tidak dipakai oleh workflow staging. Tidak perlu diisi.

## 3. Deploy staging (setiap kali mau deploy)

1. **Push** commit yang mau di-deploy ke branch `1-staging`. Ini tidak menjalankan apa pun.
2. **Jalankan workflow** dengan salah satu cara:
   - Di GitHub: **Actions → CI/CD → Run workflow**, pilih branch `1-staging`, lalu klik
     **Run workflow**.
   - Dari terminal: `gh workflow run ci-cd.yml --ref 1-staging`
3. Tunggu sampai job selesai. Urutannya:
   1. `frontend`, `laravel`, `analytics`: test
   2. `production-images`: build image
   3. `deploy-staging`: deploy ke server
4. Job `deploy-staging` di server melakukan ini:
   1. Masuk ke `STAGING_DEPLOY_DIR`.
   2. `git fetch origin 1-staging`, lalu `git checkout -f -B 1-staging origin/1-staging`.
   3. Menjalankan `scripts/deploy-vps.sh --skip-pull` dengan
      `DEPLOY_BRANCH=1-staging`, `DEPLOY_ENV_FILE=.env.staging`, dan
      `DEPLOY_PROJECT_NAME=osn-staging`.
5. Setelah job hijau, lakukan verifikasi (bagian 4).

## 4. Verifikasi

Di server, dari direktori checkout:

```bash
export APP_ENV_FILE=.env.staging
COMPOSE="docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml"

$COMPOSE ps
curl -fsS http://127.0.0.1:8091/healthz
curl -fsS http://127.0.0.1:8090/up
$COMPOSE exec -T analytics-api curl -fsS http://localhost:8000/health
$COMPOSE logs --tail=100 backend
```

Semua perintah di atas harus berhasil. Setelah itu, cek dari luar:

- `https://stag.mrcln0305.xyz` terbuka.
- `https://stag-api.mrcln0305.xyz/up` mengembalikan respons sukses.
- Login dengan akun staging berhasil.

## 5. Data staging

- **Jangan** menyalin volume `app_pgdata` atau dump produksi mentah ke staging. Tabel
  `users` berisi email, nama, dan password hash. Tabel `personal_access_tokens` berisi
  token API.
- Isi database staging dengan seed, atau dengan dump yang sudah dianonimkan: email,
  nama, dan token diganti atau dikosongkan.
- Database staging dibuat baru: `osn_readiness_staging` di project `osn-staging`.
  Jangan mengganti nama database produksi untuk dipakai sebagai staging.

## 6. Rollback

Push tidak men-deploy, jadi rollback dilakukan dalam tiga langkah:

1. Di terminal: `git revert <sha>` di branch `1-staging`, lalu `git push origin 1-staging`.
2. Jalankan workflow manual dengan branch `1-staging` (bagian 3).
3. Verifikasi (bagian 4).

Migrasi tidak dibalik otomatis. Untuk mengosongkan total database staging, jalankan
perintah berikut. **Ini menghapus semua data di project `osn-staging`**, termasuk volume
`app_pgdata` dan `laravel_public`:

```bash
cd <path checkout di VPS>
export APP_ENV_FILE=.env.staging
docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml down -v
```

Selalu pakai `-p osn-staging`. Tanpa flag itu, perintah bisa mengenai project produksi.

## 7. Sebelum deploy pertama

Jalankan workflow manual sekali dengan branch `1-staging`, lalu pastikan:

- Job `deploy-staging` **tidak** dilewati. Kalau dilewati, variable di bagian 2.3 belum
  terbaca.
- Verifikasi di bagian 4 lolos.

## 8. Masalah umum

| Gejala | Penyebab | Tindakan |
| --- | --- | --- |
| Job `deploy-staging` dilewati | Variable `DEPLOY_STAGING_ENABLED` atau `STAGING_DEPLOY_DIR` belum ada, atau tidak terbaca | Isi sebagai repository variable (bagian 2.3) |
| `Missing .env.staging` | File env belum dibuat | Ikuti bagian 2.2 langkah 3–4 |
| `APP_KEY must be a generated base64-encoded 32-byte Laravel key.` | Format `APP_KEY` salah | Pakai `base64:` + `openssl rand -base64 32` |
| `... must be a 64-character hexadecimal secret.` | Format secret salah | Pakai `openssl rand -hex 32` |
| `env file ... .env.production not found` saat compose manual | `APP_ENV_FILE` belum di-export | `export APP_ENV_FILE=.env.staging` |
| Frontend memanggil API produksi | `VITE_API_BASE_URL` salah saat build | Perbaiki `.env.staging`, lalu deploy ulang |

## 9. Catatan

- Job staging dan produksi **tidak** saling mengunci. Kalau `STAGING_DEPLOY_DIR` sama
  dengan direktori produksi, jangan jalankan keduanya bersamaan. Lebih aman memakai
  direktori terpisah.
- Staging memakai `Dockerfile.production` yang sama dengan produksi. Bug yang muncul di
  staging kemungkinan besar juga ada di produksi.
