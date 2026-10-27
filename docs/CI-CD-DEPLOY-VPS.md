# CI/CD GitHub Actions untuk VPS

Workflow `.github/workflows/ci-cd.yml` menjalankan test dan build untuk
frontend, Laravel, dan layanan analytics. Workflow hanya melakukan deploy
otomatis setelah semua job berhasil pada branch `monorepo`. Pull request hanya
menjalankan CI, tidak mengakses VPS.

## Alur

1. Pull request atau push ke `monorepo` menjalankan test aplikasi.
2. Jika semua test lolos, GitHub Actions memvalidasi konfigurasi Compose
   production dan membangun image production.
3. Hanya push ke `monorepo` atau dispatch pada branch `monorepo` yang melanjutkan
   ke deployment.
4. GitHub Actions masuk ke VPS via SSH. VPS menarik commit terbaru, membangun
   image, menjalankan migrasi Laravel, lalu mengaktifkan queue worker.

Workflow memakai GitHub-hosted runner, jadi VPS harus menerima koneksi SSH dari
runner pada port SSH. Batasi akses SSH sebisa mungkin. Jika firewall VPS
memerlukan allowlist sumber yang tetap, gunakan self-hosted runner atau solusi
jaringan privat karena rentang IP GitHub-hosted runner berubah.

## Persiapan VPS

Pasang Docker Engine dan Docker Compose Plugin, reverse proxy, serta TLS.
Pastikan port publik hanya membuka SSH yang dibatasi, HTTP, dan HTTPS. Compose
production mengikat frontend ke `127.0.0.1:8081` dan API ke `127.0.0.1:8080`.
Konfigurasikan reverse proxy:

| Hostname | Tujuan di VPS |
| --- | --- |
| `siap-osn.id` dan `www.siap-osn.id` | `http://127.0.0.1:8081` |
| `api.siap-osn.id` | `http://127.0.0.1:8080` |

Database dan FastAPI hanya berada di jaringan Docker. Jangan membuka port
`5432`, `8000`, atau `8001` ke internet.

Buat pengguna deployment dengan akses Docker dan SSH key khusus. Untuk repo
private, pasang deploy key **read-only** pada VPS agar checkout bisa menarik
branch `monorepo`. Jangan menyimpan private key deploy tersebut di repo.

Clone repo pada VPS di path tetap, checkout branch `monorepo`, lalu salin
environment file:

```bash
sudo mkdir -p /opt/osn-monorepo
sudo chown "$USER":"$USER" /opt/osn-monorepo
git clone --branch monorepo <URL-REPO> /opt/osn-monorepo
cd /opt/osn-monorepo
cp .env.production.example .env.production
chmod 600 .env.production
```

Isi `.env.production` di VPS. Buat key aplikasi Laravel dan rahasia acak:

```bash
openssl rand -base64 32
openssl rand -hex 32
```

Gunakan hasil base64 dengan awalan `base64:` untuk `APP_KEY`. Gunakan nilai
hex acak yang berbeda untuk `APP_DB_PASSWORD` dan `INTERNAL_API_TOKEN`.
Sesuaikan `APP_URL`, `FRONTEND_URL`, dan `VITE_API_BASE_URL` dengan domain
production. Simpan file tersebut di VPS saja, jangan commit.

Jalankan deployment pertama kali sebagai pengguna deploy:

```bash
cd /opt/osn-monorepo
bash scripts/deploy-vps.sh
```

Skrip menarik versi terbaru branch `monorepo`, memvalidasi format secret,
membangun image lokal di VPS, memulai database/API/frontend, menjalankan
migrasi, lalu menjalankan queue worker. Database disimpan di named volume
`app_pgdata`; file publik Laravel disimpan di volume `laravel_public`. Backup
berkala dan uji restore tetap perlu dikonfigurasi.

## Konfigurasi GitHub

Buat GitHub Environment bernama `production` pada repository, lalu tambahkan
repository variable `DEPLOY_VPS_ENABLED=true` di **Settings > Secrets and
variables > Actions > Variables** hanya setelah VPS siap menerima deployment.
Tanpa variable tersebut job deploy dilewati, sedangkan test dan build tetap
berjalan. Tambahkan environment secrets berikut sebelum mengaktifkannya:

| Secret | Nilai |
| --- | --- |
| `VPS_HOST` | IP atau hostname VPS yang dapat dijangkau GitHub Actions |
| `VPS_USER` | Pengguna deploy di VPS |
| `VPS_SSH_PRIVATE_KEY` | Private SSH key yang cocok dengan `authorized_keys` pengguna deploy |
| `VPS_KNOWN_HOSTS` | Baris host key VPS yang sudah diverifikasi |
| `VPS_DEPLOY_PATH` | Path absolut checkout, misalnya `/opt/osn-monorepo` |

Ambil host key dari VPS melalui kanal tepercaya, lalu verifikasi fingerprint
secara terpisah sebelum menyimpan nilainya di `VPS_KNOWN_HOSTS`. Workflow
mengaktifkan `StrictHostKeyChecking`, sehingga tidak menerima host key yang
belum dipercaya.

Tambahkan proteksi approval pada environment `production` bila deployment
harus menunggu persetujuan. Jangan membuat secret dengan nilai dummy di GitHub.

## Environment production

Contoh variabel ada di [.env.production.example](../.env.production.example).
File itu hanya template dan tidak boleh berisi secret sungguhan. File final
`.env.production` harus ada pada VPS dan memiliki permission terbatas.

Frontend memakai `VITE_API_BASE_URL` saat image dibangun. Jika domain API
berubah, perbarui `.env.production` di VPS lalu jalankan deployment ulang.

## Pemeriksaan dan pemulihan

- Status service: `docker compose --env-file .env.production -f docker-compose.prod.yml ps`
- Log: `docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=100`
- Health frontend: `curl -fsS http://127.0.0.1:8081/healthz`
- Health Laravel: `curl -fsS http://127.0.0.1:8080/up`
- Health analytics: `docker compose --env-file .env.production -f docker-compose.prod.yml exec analytics-api curl -fsS http://localhost:8000/health`

Jika rilis bermasalah, revert commit pada branch `monorepo`; workflow akan
menjalankan rangkaian deploy untuk commit hasil revert. Backup database sebelum
migrasi yang mengubah atau menghapus data.

## File yang digunakan workflow

- `docker-compose.prod.yml`: service production, network privat, dan port lokal
  untuk reverse proxy.
- `apps/siap-osn-fe/Dockerfile.production`: membangun bundle Vue dan
  menyajikannya melalui Nginx.
- `apps/Osn-Readiness-Web/Dockerfile.production`: Apache/PHP production untuk
  API dan queue worker.
- `scripts/deploy-vps.sh`: skrip deploy yang dipanggil workflow di VPS.
- `.env.example`: tetap untuk development lokal. Gunakan
  `.env.production.example` sebagai template environment production.
