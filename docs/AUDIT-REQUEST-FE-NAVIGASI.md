# Audit Request Frontend: Kenapa Klik Sidebar Selalu Request Ulang

Pelengkap [AUDIT-BEBAN-SERVER-DAN-REQUEST.md](AUDIT-BEBAN-SERVER-DAN-REQUEST.md), khusus
`apps/siap-osn-fe/src`. Berdasarkan baca kode (belum diukur di browser). Angka dihitung
untuk siswa yang sudah pretest dengan T tingkat terbuka (T=2 contoh).

## Catatan sebelum membaca tab Network

- Filter **Fetch/XHR**. Baris `OPTIONS` adalah preflight CORS, bukan request aplikasi.
  Backend memberi `cors.max_age = 0` (`config/cors.php:35`), jadi tiap request
  tampil **dua kali** (OPTIONS + GET). Terbukti pada pengukuran di bawah. Memperbaikinya cukup `max_age: 7200` di backend
  dan akan memangkas baris yang Anda lihat sekitar setengah.
- `AppLayout` adalah layout induk, jadi tidak ter-mount ulang saat klik antar sidebar.
  Pemicu request ada di **view** tiap halaman, bukan di layout. (Pemanggilan
  `fetchDashboard()` di `AppLayout.vue:56` hanya sekali per masuk area siswa, tetap
  sebaiknya dihapus karena `DashboardView` sudah memanggilnya.)

## Hasil pengukuran (browser nyata, sebelum refactor)

Metode: backend Laravel + Postgres lokal, dev server Vite, Chromium non-headless. Siswa
contoh dengan 4 pretest selesai, 2 hasil simulasi, 2 tingkat (hanya kabupaten yang
terbuka, jadi T=1). `page.on('request')` mencatat semua request ke `/api` selama:
login, lalu klik sidebar berurutan dua putaran (semua klik ulang dalam <60 dtk), lalu F5.
Angka di bawah dipisah: **aplikasi** (GET/POST) dan **preflight OPTIONS**.

| Langkah | Aplikasi | OPTIONS | Total di Network |
|---|---|---|---|
| Login + dashboard pertama | 9 | 9 | 18 |
| Materi (klik pertama) | 3 | 3 | 6 |
| Simulasi Seleksi (klik pertama) | 12 | 12 | 24 |
| Riwayat Hasil (klik pertama) | 10 | 10 | 20 |
| Pemetaan Kompetensi (klik pertama) | 6 | 6 | 12 |
| Dashboard (klik ulang) | 0 | 0 | 0 |
| Materi (klik ulang) | 1 | 1 | 2 |
| **Simulasi Seleksi (klik ulang)** | **12** | 12 | 24 |
| **Riwayat Hasil (klik ulang)** | **8** | 8 | 16 |
| Pemetaan Kompetensi (klik ulang) | 5 | 5 | 10 |
| F5 di Dashboard | 9 (+1 `/auth/me`) | 9 | 18 |
| **Total satu sesi** | **75** | **75** | **150** |

Temuan dari data ini:

1. **Setiap request API muncul dua kali di Network** (OPTIONS lalu GET/POST), tepat 75
   pasang. Itu preflight CORS dari `cors.max_age = 0`. Satu baris config di backend
   menghilangkan separuh dari semua baris yang terlihat.
2. **Simulasi Seleksi memuat ulang semuanya tiap klik**, bahkan klik kedua dalam 60 dtk:
   12 request identik (`/dashboard`, `/tingkat`, `/materi`, dua `/riwayat`,
   `/hasil-simulasi/{id}` **dua kali**, `/pretest/{id}`, `/simulasi`, `/simulasi/syarat`, ...).
   Ini akibat `force:true` (`SimulasiView.vue:316-318`). Dengan data kecil pun sudah 12.
3. **`GET /pretest/{id}` adalah endpoint terbanyak: 19 dari 75 request aplikasi.**
   Setiap kunjungan Riwayat mengambilnya 4x (satu per item riwayat; makin banyak riwayat
   makin banyak, sampai 100), Pemetaan 3x, Materi dan Simulasi masing-masing 1x. Isinya
   hasil final yang tidak pernah berubah.
4. **`GET /materi?tingkat_id=` 14 kali** di satu sesi: Riwayat dan Pemetaan mengambilnya
   langsung tanpa cache store (di Riwayat 3x dalam satu kunjungan di klik pertama).
5. `/tingkat` 6 kali dan `/hasil-simulasi/{id}` 6 kali (juga data yang stabil).
6. Dashboard dan klik ulang Materi sudah murah (cache 60 dtk bekerja). Masalahnya hanya
   pada halaman yang memakai `force` atau panggilan service langsung.
7. Ada yang meleset dari perkiraan awal dari kode: Materi hanya 3 request (bukan T+1+),
   dan Simulasi 12 (bukan ~16) pada T=1. Pada T=2 kedua angka naik.

Request terbanyak (aplikasi saja, satu sesi):

| Endpoint | Kali |
|---|---|
| `GET /pretest/{id}` | 19 |
| `GET /materi?tingkat_id=` | 14 |
| `GET /tingkat` | 6 |
| `GET /hasil-simulasi/{id}` | 6 |
| `GET /riwayat?jenis=pretest` | 5 |
| `GET /dashboard`, `/riwayat` (5 terbaru), `/riwayat?jenis=simulasi`, `/riwayat?jenis=pretest&tingkat_id` | masing-masing 4 |

Pengukuran diulang dengan skrip yang sama setelah refactor untuk membandingkan.

## Hasil setelah perbaikan (sudah diterapkan, diukur dengan skrip yang sama)

Yang diubah:
- `src/lib/cache.js` (baru): cache GET di memori dengan dedup request yang sedang berjalan, TTL,
  `invalidasi()` dan `kosongkan()`. Request yang kedaluwarsa karena mutasi saat berjalan tidak
  disimpan (penghitung generasi).
- Cache dipasang di lapisan service, bukan di view: `belajarService.daftar/detail` (5/2 mnt),
  `pretestService.tingkat` (5 mnt), `simulasiService.daftar/syarat` (2 mnt),
  `riwayatService.daftar` (30 dtk). `pretestService.lihat` dan `simulasiService.lihat`
  menyimpan **hanya hasil final, permanen**; paket pengerjaan/menunggu selalu diambil ulang.
  Yang di-cache adalah respons mentah, jadi tiap pemanggil tetap mendapat objek hasil mapping
  sendiri.
- `lib/api.js`: mutasi sukses (POST/PUT/PATCH/DELETE) memanggil `invalidasi()`. Hasil final
  tidak ikut dibuang. `stores/auth.js`: `resetDataAkun()` memanggil `kosongkan()` (logout,
  login, sesi habis), sehingga data akun lain tidak terbaca.
- `force: true` saat mount dihapus di `SimulasiView` dan `RiwayatView`. `force` setelah aksi
  (submit, tandai selesai) dipertahankan.
- `stores/materi.js`: `inflight` kini dicocokkan dengan `tingkatId` (bug ganti tingkat cepat).
- Backend `config/cors.php`: `max_age` 0 menjadi 7200, sehingga preflight di-cache browser.

| Langkah | Aplikasi sebelum | Aplikasi sesudah | Network sebelum | Network sesudah |
|---|---|---|---|---|
| Login + dashboard | 9 | 9 | 18 | 18 |
| Materi (pertama / ulang) | 3 / 1 | 1 / 0 | 6 / 2 | 2 / 0 |
| Simulasi (pertama / ulang) | 12 / 12 | 3 / 0 | 24 / 24 | 6 / 0 |
| Riwayat (pertama / ulang) | 10 / 8 | 5 / 1 | 20 / 16 | 10 / 1 |
| Pemetaan (pertama / ulang) | 6 / 5 | 1 / 0 | 12 / 10 | 2 / 0 |
| F5 di Dashboard | 9 | 9 | 18 | 10 |
| **Total satu sesi** | **75** | **29** | **150** | **49** |

Request aplikasi turun 61%, dan baris di tab Network turun 67% (preflight praktis hilang:
49 baris untuk 29 request). Klik ulang Simulasi/Materi/Pemetaan dalam TTL kini 0 request.
Pada F5 hanya 1 preflight yang tersisa (10 baris untuk 9 request).

Belum dikerjakan (perlu backend atau perubahan lebih besar): menggabungkan agregat ke
`/dashboard` (login masih 9), ringkasan benar/total di `/riwayat` (masih satu
`/pretest/{id}` per item riwayat saat kunjungan pertama), `fetchDashboard` di `AppLayout`
(tidak menambah request karena sudah dedup dengan `DashboardView`), polling nilai lewat GET.

## Perkiraan dari kode (sebelum diukur)

| Klik | Request | Penyebab utama |
|---|---|---|
| Dashboard (cache >60 dtk) | 7+T: `/dashboard`, `/tingkat`, `/materi` x T, `/riwayat` (pretest), `/pretest/{id}`, `/riwayat` (simulasi, per_page=100), `/hasil-simulasi/{id}`, `/riwayat` (5 terbaru) | cache hanya 60 dtk; fan-out |
| Materi | >= T+1 **setiap klik** | `belajarService.daftar` per tingkat dipanggil langsung (`MateriView.vue:162`), melewati store |
| Simulasi Seleksi | ~10+3T **setiap klik** (T=2: ~16) | `fetchDashboard({force:true})` (`SimulasiView.vue:316`), `muatTingkat({force:true})` (`:318`), `daftar`+`syarat` x T (`:81-82`), detail hasil + riwayat (`:262-296`) |
| Riwayat Hasil | 2+T+N **setiap klik**, N sampai 100 | `muatTingkat({force:true})` (`RiwayatView.vue:210`), `belajarService.daftar` x T (`:224`), **`/pretest/{id}` per item riwayat** (`:238`, `per_page:100`) |
| Pemetaan Kompetensi | ~4-10 setiap klik | `riwayatService.daftar` (`PemetaanKompetensiView.vue:41`), `belajarService.daftar` (`:250`), `/pretest/{id}` sampai 5 (`:264`) |
| Progress belajar (`ProgressView`) | 3+ setiap klik | `muatRuang` (`daftar`+`syarat`) tanpa cache (`stores/simulasi.js:89-110`) |

## Akar masalah

1. **Tiga lapis pengambilan data tidak konsisten.** Sebagian lewat store ber-cache
   (`progress`, `materi`, `pretest.muatTingkat`), sebagian memanggil service langsung dari
   view. Panggilan langsung tidak punya cache sama sekali. Ada 9 pemanggilan GET langsung
   di view siswa (`MateriView:137,162`, `PemetaanKompetensiView:41,250,264`,
   `RiwayatView:216,224,238`, `SimulasiView:81`).
2. **`force: true` pada mount.** `SimulasiView:316-318`, `RiwayatView:210`,
   `UjianSimulasiView:143-144`, `DashboardView:37`, `MateriView:268` memotong cache yang
   sebenarnya ada, sehingga klik sidebar apa pun memuat ulang.
3. **TTL tidak sesuai sifat datanya.** Cache dashboard 60 dtk (`progress.js:15`) dan
   daftar materi 60 dtk (`materi.js:7`) terlalu pendek untuk data yang berubah hanya saat
   siswa menyelesaikan sesuatu. Sebaliknya, **hasil pretest/simulasi yang sudah selesai
   tidak pernah berubah**, tetapi `/pretest/{id}` dan `/hasil-simulasi/{id}` diambil
   ulang setiap kali.
4. **N+1 di Riwayat.** Satu request detail per item riwayat hanya untuk menghitung
   `benar`/`totalSoal`/pemetaan di klien (`RiwayatView:233-250`).
5. **Duplikasi antar halaman.** Dashboard, Riwayat, Pemetaan, dan Materi sama-sama
   mengambil `/materi` per tingkat dan `/riwayat` pretest + `/pretest/{id}`.
6. **Tidak ada invalidasi berbasis kejadian.** Karena tidak ada cara memberi tahu cache
   bahwa data berubah, solusinya selalu `force: true`. Itu yang memicu poin 2.

## Rencana perbaikan

### Tahap 1: perubahan kecil, langsung terasa (tanpa arsitektur baru)
1. Hapus `force: true` di `SimulasiView`, `RiwayatView`, `UjianSimulasiView` mount.
   Pakai cache; paksa hanya setelah aksi yang mengubah data (submit, selesai submateri).
2. Hapus `fetchDashboard()` dari `AppLayout.vue:56`.
3. Semua `belajarService.daftar` di view diganti `materi.fetchDaftar({tingkatId})`
   (cache per tingkat; ubah `stores/materi.js` agar menyimpan `Map<tingkatId, daftar>`
   dan memeriksa `tingkatId` pada `inflight`, saat ini bug di `materi.js:22`).
4. Cache permanen per `id` untuk `pretestService.lihat` dan `simulasiService.lihat`
   **bila `jenis === 'hasil'`** (data final).
5. Setelah PUT submateri selesai, ubah state lokal saja (`MateriView.vue:268`) tanpa
   `fetchDashboard({force:true})`.

### Tahap 2: satu lapisan cache untuk semua GET (menyelesaikan akar #1, #3, #6)
Tambah `src/lib/cache.js` dan pakai di tiap fungsi GET pada `services/*.js`, sehingga
view dan store tidak perlu tahu soal cache:

```js
const entri = new Map() // key -> { nilai, kadaluarsa, janji }

export function ambil(key, fetcher, { ttl = 60_000, force = false } = {}) {
  const e = entri.get(key)
  if (!force && e) {
    if (e.janji) return e.janji                        // dedup request yang sedang jalan
    if (e.kadaluarsa > Date.now()) return Promise.resolve(e.nilai)
  }
  const janji = fetcher()
    .then((nilai) => { entri.set(key, { nilai, kadaluarsa: Date.now() + ttl }); return nilai })
    .catch((err) => { entri.delete(key); throw err })
  entri.set(key, { ...(e ?? {}), janji })
  return janji
}

export function invalidasi(awalan) {
  for (const k of entri.keys()) if (k.startsWith(awalan)) entri.delete(k)
}
export const kosongkan = () => entri.clear() // panggil saat logout/$reset
```

Usulan TTL dan invalidasi:

| Data | Key | TTL | Invalidasi saat |
|---|---|---|---|
| `/tingkat` | `tingkat` | 10 mnt | submit pretest/simulasi |
| `/materi?tingkat_id=` | `materi:{id}` | 5 mnt | tandai materi/submateri selesai |
| `/materi/{id}` | `materi-detail:{id}` | 5 mnt | tandai submateri selesai |
| `/pretest/{id}` (hasil) | `pretest:{id}` | tak terbatas | tidak perlu |
| `/hasil-simulasi/{id}` (hasil) | `hasil-sim:{id}` | tak terbatas | tidak perlu |
| `/riwayat?...` | `riwayat:{query}` | 30 dtk | submit apa pun |
| `/simulasi` + `syarat` per tingkat | `sim:{id}` | 2 mnt | submit pretest/simulasi |
| `/dashboard` | `dashboard` | 60 dtk | submit apa pun, tandai selesai |

Panggil `invalidasi(...)` di aksi yang mengubah data (`kumpulkan`, `tandaiSelesai`,
`tandaiSubmateriSelesai`), dan `kosongkan()` di `auth.$reset()`/logout supaya data
siswa A tidak terlihat siswa B di browser yang sama. Setelah ini `force` hampir tidak
diperlukan lagi.

Alternatif: `@tanstack/vue-query` (staleTime, dedup, `invalidateQueries`, retry bawaan).
Lebih lengkap tetapi menambah dependensi dan menuntut store ditulis ulang. Untuk basis
kode ini, `lib/cache.js` di lapisan service lebih murah dan tidak menyentuh store.

### Tahap 3: stale-while-revalidate (terasa instan)
Untuk halaman yang sering dibuka (Dashboard, Materi, Riwayat): render data cache dulu,
lalu muat ulang diam-diam bila TTL lewat. Tidak mengurangi request, tetapi menghilangkan
spinner saat klik sidebar bolak-balik.

### Tahap 4: bagian yang perlu backend
- `/dashboard` mengembalikan juga: jumlah materi selesai/total, rata-rata dan jumlah
  simulasi, 5 riwayat terbaru. Menghapus 5-6 request susulan (FE-1 di audit utama).
- `/riwayat` menyertakan ringkasan `benar`/`total_soal` per item pretest. Menghapus N+1
  `/pretest/{id}` di Riwayat dan Pemetaan.
- Header `ETag` + `Cache-Control: private, max-age=...` untuk GET statis
  (`/tingkat`, `/materi`): request tetap ada tetapi balasan `304` murah.

## Target setelah perbaikan (siswa sudah pretest, T=2)

| Klik (terukur, T=1, N=4) | Sekarang | Target tahap 1-2 | Target tahap 4 |
|---|---|---|---|
| Login + dashboard | 9 | ~9 | 2-3 |
| Dashboard klik ulang (<60 dtk) | 0 | 0 | 0 |
| Materi (pertama / ulang) | 3 / 1 | 3 / 0 | 2 / 0 |
| Simulasi (pertama / ulang) | 12 / 12 | 4 / 0 | 3 / 0 |
| Riwayat (pertama / ulang) | 10 / 8 | 2 / 1 | 1 / 1 |
| Pemetaan (pertama / ulang) | 6 / 5 | 1 / 0 | 1 / 0 |
| Total satu sesi uji | 75 (150 dengan OPTIONS) | ~30 | ~20 |

Dengan `max_age` CORS dan hasil final yang di-cache permanen, request OPTIONS dan
request berulang untuk data final hilang sepenuhnya.

## Cara memverifikasi di browser

1. DevTools -> Network -> filter Fetch/XHR, centang **Preserve log**, **jangan**
   centang Disable cache (tidak memengaruhi cache aplikasi, tetapi memengaruhi 304).
2. Login siswa yang sudah pretest, tunggu halaman tenang, **Clear**.
3. Klik sidebar berurutan: Materi -> Simulasi -> Riwayat -> Dashboard -> Materi, lalu
   hitung baris per klik. Bandingkan dengan tabel di atas.
4. Ulangi setelah perbaikan; klik kedua pada halaman yang sama dalam TTL harus 0 baris.
