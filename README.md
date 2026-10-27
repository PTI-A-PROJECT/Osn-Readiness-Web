# OSN Monorepo

Penggabungan tiga repo proyek OSN ke dalam satu repo, **tanpa menghapus riwayat commit masing-masing**.

## Struktur

| Path | Asal repo | Branch yang digabung | Isi |
|---|---|---|---|
| `apps/Osn-Readiness-Web/` | `Aditya27T/Osn-Readiness-Web` | `siap-integrasi` | Backend Laravel + API |
| `apps/data-analytics/` | `PTI-A-PROJECT/data-analytics` | `integrasi` | Backend Python (FastAPI + Alembic) |
| `apps/siap-osn-fe/` | `PTI-A-PROJECT/Siap-osn-Fe` | `dev` | Frontend Vue (Vite) |

## Menjalankan dengan Docker Compose

Seluruh 3 aplikasi (Frontend, Backend, Layanan Hitung) serta database PostgreSQL untuk backend dapat dijalankan bersamaan dengan Docker:

```bash
# 1. Salin environment variable (opsional bila ingin kustomisasi port)
cp .env.example .env

# 2. Build dan jalankan seluruh container
docker compose up --build
```

Setelah berjalan:
- **Frontend (Vue/Vite):** http://localhost:5173
- **Backend API (Laravel):** http://localhost:8000
- **Layanan Hitung (FastAPI - Stateless):** http://localhost:8001 (internal: http://analytics-api:8000)
- **Database Backend (Postgres):** `localhost:5432`

Untuk menghentikan:
```bash
docker compose down
```

## Riwayat commit tetap utuh

Setiap repo digabung dengan `git subtree add` tanpa `--squash`, jadi seluruh commit asli
masih ada di repo ini. Hanya lokasi file yang berubah mengikuti struktur folder di atas.

Melihat seluruh riwayat commit (author, tanggal, pesan) sesuai urutan waktu:

```bash
git log --oneline --graph
git log --oneline --author=<nama>
```

Melihat isi sebuah commit tertentu:

```bash
git show <sha>
```

### Catatan: `git log <path>` terbatas

Karena tiap repo digabung lewat merge commit, path file berubah mengikuti prefix folder
(`src/...` menjadi `apps/siap-osn-fe/src/...`). Git tidak bisa menelusuri mundur melewati
merge untuk path lama, jadi `git log -- apps/siap-osn-fe/src/...` hanya menampilkan
commit penggabungan, bukan seluruh riwayat file tersebut.

Semua commit lama tetap ada dan bisa dibaca lewat `git log --graph` atau `git show <sha>`.
Cara ini dipilih agar SHA commit tetap identik dengan repo GitHub asalnya.

Melihat commit dari repo tertentu saja:

## Repo asal tetap utuh

Ketiga repo asal tidak diubah oleh proses penggabungan ini (hanya ada satu commit
penutup untuk file yang sebelumnya belum di-commit). Keduanya bisa dipakai berdampingan.

---

## Tim Kontributor

Seluruh kontributor dari ketiga repositori proyek yang digabung ke dalam monorepo ini:

| Bagian | Kontributor | GitHub / Email |
|---|---|---|
| **Backend** (`Osn-Readiness-Web`) | Aditya Eka Rahmadani | [@Aditya27T](https://github.com/Aditya27T) |
| | Sultonaru | `arifsetiawan.achmad2004@gmail.com` |
| | Haman Haris | `hamanharis308@gmail.com` |
| **Frontend** (`siap-osn-fe`) | Aditya Eka Rahmadani | [@Aditya27T](https://github.com/Aditya27T) |
| | Alvina | `amaliavina34@gmail.com` |
| | Elvira | [@Elvira2334](https://github.com/Elvira2334) |
| | Fajar Purba | [@Fajarpurba](https://github.com/Fajarpurba) |
| | Nikola Okin | [@OKIN0902](https://github.com/OKIN0902) |
| | Ibra Fahro (bjirkah) | `achmadibraalfahro@gmail.com` |
| | Anisatul Qomariyah (nisajs) | `anisatulqomariyah95@gmail.com` |
| | Bimo Taraqie | `bimotaraqie87@gmail.com` |
| **Data Analytics** (`data-analytics`) | Aditya Eka Rahmadani | [@Aditya27T](https://github.com/Aditya27T) |
| | Muhammad Hanif Abdur Razaq | [@Razzoom23](https://github.com/Razzoom23) |
| | Fakhri Ahmadi (Nexus) | `fakhriahmadi559@gmail.com` |
| | Falih (PalihGit) | `falih.thefal@gmail.com` |
| | Putra Lyan (PuLyan123) | `putralyan127@gmail.com` |
| | Hilmi Zakin Putra | `hilmizakin12@gmail.com` |
| | Dea Alensa | `dea.alensa11@gmail.com` |
