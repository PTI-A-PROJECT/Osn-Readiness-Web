# Panduan Staging SIAP OSN

Panduan ini khusus untuk environment staging. Untuk produksi, lihat
[CI-CD-DEPLOY-VPS.md](CI-CD-DEPLOY-VPS.md).

## Ringkasan

| | Produksi | Staging |
| --- | --- | --- |
| Branch | `monorepo` | `staging` |
| Cara deploy | otomatis saat push ke `monorepo` | manual: Run workflow dengan branch `staging` |
| Job deploy | `deploy` | `deploy-staging` |
| Env file | `.env.production` | `.env.staging` |
| Project Compose | default (nama direktori) | `osn-staging` |
| Port host | 8081 (frontend), 8080 (backend) | 8091 (frontend), 8090 (backend) |
| Environment GitHub | `production` | `staging` |

Push ke branch `staging` tidak memicu action. Deploy staging hanya jalan lewat
dispatch manual.

## Prasyarat

- Runner `self-hosted` online dan bisa menulis di direktori checkout staging.
- Environment GitHub `staging` dibuat, dengan variable:
  - `DEPLOY_STAGING_ENABLED=true`
  - `STAGING_DEPLOY_DIR=<path checkout di VPS>`
- Reverse proxy, DNS, dan TLS untuk `staging.<domain>` dan `api-staging.<domain>`.
- Redirect URI staging terdaftar di Google OAuth client.

## Setup sekali di server

```bash
cd <path checkout di VPS>
git fetch origin staging
git checkout staging
cp .env.production.example .env.staging
chmod 600 .env.staging
```

Isi `.env.staging`:

- `APP_URL=https://api-staging.<domain>`, `FRONTEND_URL=https://staging.<domain>`,
  `VITE_API_BASE_URL=https://api-staging.<domain>/api`.
- `GOOGLE_REDIRECT_URI=https://api-staging.<domain>/auth/google/callback`, dengan
  `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` milik client staging.
- `APP_KEY`: `base64:` diikuti hasil `openssl rand -base64 32`. Jangan salin dari produksi.
- `APP_DB_PASSWORD` dan `INTERNAL_API_TOKEN`: hasil `openssl rand -hex 32`. Keduanya harus
  berbeda satu sama lain dan berbeda dari produksi.
- `APP_DB_DATABASE=osn_readiness_staging`.
- `FRONTEND_HOST_PORT=8091` dan `BACKEND_HOST_PORT=8090`.

Reverse proxy:

- `staging.<domain>` diarahkan ke `127.0.0.1:8091`.
- `api-staging.<domain>` diarahkan ke `127.0.0.1:8090`, dengan header `Host`,
  `X-Forwarded-Proto`, dan `X-Forwarded-For` diteruskan.

## Deploy

1. Push commit ke branch `staging`. Tidak ada action yang berjalan.
2. Jalankan workflow secara manual, dengan salah satu cara:
   - GitHub: Actions → CI/CD → Run workflow → Branch: `staging`.
   - CLI: `gh workflow run ci-cd.yml --ref staging`.
3. Job berjalan berurutan: `frontend`, `laravel`, `analytics` (test), lalu
   `production-images` (build), lalu `deploy-staging`.
4. `deploy-staging` di server masuk ke `STAGING_DEPLOY_DIR`, lalu
   `git checkout -f -B staging origin/staging`, dan menjalankan
   `scripts/deploy-vps.sh` dengan `DEPLOY_BRANCH=staging`,
   `DEPLOY_ENV_FILE=.env.staging`, dan `DEPLOY_PROJECT_NAME=osn-staging`.

## Verifikasi setelah deploy

Di server:

```bash
cd <path checkout di VPS>
export APP_ENV_FILE=.env.staging
docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml ps
curl -fsS http://127.0.0.1:8091/healthz
curl -fsS http://127.0.0.1:8090/up
docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml \
  exec -T analytics-api curl -fsS http://localhost:8000/health
docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml \
  logs --tail=100 backend
```

Lalu buka `https://staging.<domain>` dan cek login.

## Data

- Jangan menyalin volume `app_pgdata` atau dump produksi mentah. Tabel `users` berisi
  email, nama, dan password hash. Tabel `personal_access_tokens` berisi token API.
- Isi database staging dengan seed, atau dengan dump yang sudah dianonimkan: email,
  nama, dan token diganti atau dikosongkan.
- Jangan mengganti nama database produksi untuk dipakai sebagai staging. Database
  staging dibuat baru: `osn_readiness_staging` di project `osn-staging`.

## Rollback

Push tidak men-deploy, jadi rollback dilakukan dalam dua langkah:

1. `git revert <sha>` di branch `staging`, lalu push.
2. Jalankan workflow secara manual dengan branch `staging`.

Migrasi tidak dibalik otomatis. Untuk mengosongkan total database staging, jalankan
perintah di bawah. Ini menghapus semua data di project `osn-staging`, termasuk
volume `app_pgdata` dan `laravel_public`:

```bash
cd <path checkout di VPS>
export APP_ENV_FILE=.env.staging
docker compose -p osn-staging --env-file .env.staging -f docker-compose.prod.yml down -v
```

Jangan jalankan `down -v` tanpa `-p osn-staging`. Tanpa flag itu, perintah bisa
mengenai project produksi.

## Troubleshooting

| Gejala | Penyebab | Tindakan |
| --- | --- | --- |
| `Missing .env.staging` | File belum dibuat | Ikuti bagian setup |
| `APP_KEY must be a generated base64-encoded 32-byte Laravel key.` | Format `APP_KEY` salah | Pakai `base64:` diikuti `openssl rand -base64 32` |
| `... must be a 64-character hexadecimal secret.` | Format secret salah | Pakai `openssl rand -hex 32` |
| `env file ... .env.production not found` saat menjalankan compose manual | `APP_ENV_FILE` belum di-export | `export APP_ENV_FILE=.env.staging` |
| Job `deploy-staging` dilewati | Variable `DEPLOY_STAGING_ENABLED` atau `STAGING_DEPLOY_DIR` belum diisi, atau tidak terbaca | Isi sebagai repository variable; gate `if:` job tidak dijamin membaca variable level environment |

## Catatan

- Job staging dan produksi tidak saling mengunci. Jika `STAGING_DEPLOY_DIR` sama dengan
  direktori produksi, jangan jalankan keduanya bersamaan. Pakai direktori terpisah, atau
  tambahkan concurrency di file `monorepo` sebelum berbagi direktori.
- Staging memakai `Dockerfile.production` yang sama dengan produksi. Bug yang muncul
  di staging kemungkinan besar juga ada di produksi.
