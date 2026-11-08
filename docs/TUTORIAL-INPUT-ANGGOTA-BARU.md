# Tutorial: Menambah Anggota Baru Setelah Deploy

Akun anggota dibuat oleh `AnggotaSeeder`
(`apps/Osn-Readiness-Web/database/seeders/AnggotaSeeder.php`). Daftar NIM dan nama
ada di konstanta `ANGGOTA` pada file itu. Admin cukup menjalankan satu perintah di
VPS. Fitur dan aturannya ada di [anggota.md](anggota.md); cara deploy ada di
[CI-CD-DEPLOY-VPS.md](CI-CD-DEPLOY-VPS.md).

## Cara kerja singkat

- Login anggota: kolom "Email atau NIM" diisi NIM, password awal sama dengan NIM.
- Kode referal dibuat otomatis berbentuk `OSN<NAMA>` dari kata pertama nama yang
  belum dipakai (`ACHMAD IBRA ALFAHRO` menjadi `OSNIBRA` bila `OSNACHMAD` sudah ada).
  Bila semua kata terpakai, kata pertama diberi nomor urut (`OSNBUDI2`).
- Seeder aman dijalankan berulang. NIM yang sudah punya akun dilewati: nama, kode,
  dan password-nya tidak diubah. Daftar boleh berisi anggota lama dan baru sekaligus.
- Role `anggota` dibuat oleh seeder ini sendiri.
- Seeder sengaja tidak masuk `DatabaseSeeder`, jadi tidak jalan otomatis saat deploy.
- Data tidak valid (NIM bukan 8-20 digit, NIM ganda, nama kosong) membuat seeder
  berhenti sebelum membuat akun apa pun.

## Prasyarat

Deploy fitur anggota sudah selesai. `scripts/deploy-vps.sh` menjalankan
`php artisan migrate --force`, yang menambah kolom `nim`. Cek di VPS:

```bash
cd /opt/osn-monorepo
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE exec -T backend php artisan migrate:status | grep tambah_nim
```

## Langkah

### 1. Tambah anggota di seeder

Edit `AnggotaSeeder.php`, tambahkan satu baris `['NIM', 'NAMA']` per anggota:

```php
private const ANGGOTA = [
    ['230533610158', 'ACHMAD ARIF SETIAWAN'],
    ['230533605308', 'ACHMAD IBRA ALFAHRO'],
    // tambah di sini
];
```

Nama sebaiknya huruf besar seperti data kampus. Commit lalu deploy seperti biasa
supaya file ikut masuk ke image `backend`.

### 2. Jalankan seeder di VPS

```bash
cd /opt/osn-monorepo
COMPOSE="docker compose --env-file .env.production -f docker-compose.prod.yml"
$COMPOSE exec -T backend php artisan db:seed --class=AnggotaSeeder --force
```

Keluaran berupa tabel NIM, nama, kode referal, dan status (`dibuat` atau
`dilewati`). Jalankan lagi bila ada tambahan; baris lama berstatus `dilewati`.

### 3. Periksa hasilnya

- Login `https://siap-osn.id` sebagai Super Admin, buka **Manajemen Anggota**.
  Anggota muncul di tabel, urut nama.
- Uji satu akun: login dengan NIM sebagai email dan password sama dengan NIM.
  Anggota masuk ke halaman anggota (dashboard dan Kode Referal).

## Yang disampaikan ke anggota

- Alamat: `https://siap-osn.id/login`.
- Isi kolom "Email atau NIM" dengan NIM, password sama dengan NIM.
- Kode referal ada di menu **Kode Referal**; nama pada kode bisa diganti sendiri.

## Batasan

- Belum ada fitur ganti password. Password awal sama dengan NIM, jadi akun anggota
  hanya aman selama isinya terbatas pada kode dan jumlah referal.
- Seeder hanya menambah. Mengubah nama atau NIM di daftar tidak mengubah akun yang
  sudah ada, dan menghapus baris dari daftar tidak menghapus akunnya. Edit, nonaktif,
  dan hapus anggota belum didukung. Periksa NIM satu per satu sebelum deploy.
- Daftar berisi data pribadi (NIM dan nama) dan ikut ke repository. Pastikan
  repository tetap privat.
- Jika kode yang diinginkan sudah dipakai, anggota mendapat kode bernomor
  (`OSNBUDI2`) dan dapat menggantinya dari menu **Kode Referal**.

## Alternatif: impor CSV

Untuk daftar sementara tanpa deploy, tersedia
`php artisan anggota:impor <file.csv> [--dry-run]` dengan header `nim,nama`. File
harus disalin ke container (`$COMPOSE cp file.csv backend:/tmp/a.csv`) dan hasilnya
sama dengan seeder.
