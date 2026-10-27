# Verifikasi audit 11 Oktober dan task tiga tim

Tanggal pemeriksaan: 27 Oktober 2026. Basis: working tree monorepo `03c8b4d` (`apps/Osn-Readiness-Web`, `apps/siap-osn-fe`, `apps/data-analytics`). Laporan 11 Oktober adalah potret repo terpisah, bukan status monorepo hari ini. Pemeriksaan ini membaca git dan kode serta menghitung ulang JSON. Suite aplikasi dan alur browser belum dijalankan karena `vendor/`, `node_modules/`, dan `.venv/` tidak tersedia di checkout ini. Status database aktual juga belum diperiksa.

## Hasil verifikasi klaim audit

| Temuan lama | Status kini | Bukti dan koreksi |
| --- | --- | --- |
| F1, commit impor kabupaten terisolasi (P0) | **Selesai di monorepo** | `b0b0a7b` adalah ancestor `HEAD`; `KontenKabupatenSeeder.php` dan perbaikan `KuotaLevel` ada. Riwayat menunjukkan merge `624b24a`. Status push repo asal tidak bisa disimpulkan dari checkout ini. |
| F2, paket impor dan skrip tidak ter-commit (P0) | **Selesai sebagai masalah git, masih bermasalah saat bootstrap** | `soal_osn/impor/` (294 file), skrip, dan draft skema tracked. Paket baru `soal/impor/` (301 file) juga tracked. Namun `.env.example` membiarkan `KONTEN_KABUPATEN_FOLDER` kosong; seeder melewati impor dengan warning. Compose tidak memasang folder analytics ke backend, tidak mengisi env tersebut, dan hanya menjalankan `migrate`, bukan `migrate --seed`. Clone baru tidak otomatis punya bank soal. |
| F3–F4, `dev`/`main` FE bercabang dan commit `d89d987` hanya di `dev` | **Status branch lama tidak relevan langsung; celah pengujian masih ada** | Snapshot FE monorepo sudah memuat `src/lib/pretest.js`, `soal.js`, `tingkat.js` dan durasi 60 menit. Namun `e2e/`, `playwright.config.js`, serta panduan testing yang disebut sebagai milik `main` lama tidak ada di snapshot ini. Tidak ada dasar menyatakan kedua garis lama sudah digabung utuh. |
| F5, perubahan `PretestView.vue` belum committed | **Selesai** | `waktuTampil` ada pada file tracked; hanya `README.md` root yang modified (perubahan pengguna, tidak disentuh). `docs/image.png` dan `docs/a.txt` kini tracked. |
| F6, 734 soal provinsi menunggu impor | **Angka lama benar untuk paket lama; status DB belum diketahui** | `soal_osn/impor/`: 878 kabupaten + 734 provinsi. Paket terbaru `soal/impor/`: 922 kabupaten + 814 provinsi. `impor:konten --tingkat=provinsi` tersedia, tetapi tidak ada bukti DB monorepo sudah menerima salah satu paket. |
| F7, reset password palsu | **Masih terjadi** | `ForgotPasswordForm.vue` dan `ResetPasswordForm.vue` memakai delay palsu dan memberi notifikasi sukses; form profil hanya reset input tanpa memanggil API. Backend punya route web auth, tetapi tidak ada kontrak API token untuk FE SPA pada `routes/api.php`. |
| F8, siswa tak bisa diaktifkan lagi | **Masih terjadi** | Route, service, policy, dan tombol hanya menyediakan `deactivate`; tidak ada `activate`. |
| F9, angka draft skema tak sinkron | **Perlu penjelasan sumber, bukan sekadar mengganti angka** | Draft menyebut perkiraan ~920 dan 234 tanpa label; laporan paket lama mencatat 878 dan 213. Paket baru memang berisi 922 soal kabupaten, sehingga angka ~920 mendekati paket baru, tetapi 234 tidak ada di `soal/impor/laporan.json` (paket baru memakai kategori skip berbeda). Cantumkan nama paket dan tanggal pada setiap angka. |
| F10, `peruntukan` diturunkan dari tahun | **Masih terjadi** | `scripts/susun_folder_impor.py` memakai `peruntukan_dari_tahun`; dokumen paket baru menyebut aturan transisi sampai 2012/2013–2019/2020+. Jangan gunakan nilai ini sebagai label kurikulum. |
| F11–F12, file debug dan `a.txt` | **Masih ada, kini tracked** | `test_bobot.py` tetap di root analytics dan `docs/a.txt` sudah committed. Perlu klasifikasi isi sebelum dibuang. |
| Klaim semua fase 0–7 dan test hijau | **Historis, tidak tervalidasi ulang** | Fase 3 memang tidak diulang oleh audit lama. Angka 448/169/109 berasal dari 11 Oktober; dependency aplikasi tidak tersedia untuk mengulang suite sekarang. |

## Bug dan risiko tambahan yang terverifikasi dari kode

1. **P0: dua bank soal berpotensi menggandakan soal saat keduanya diimpor.** Paket lama dan baru punya `id_sumber` berbeda. Perbandingan teks `soal` persis menunjukkan 678 baris kabupaten dan 438 provinsi beririsan. Ringkasan backend 11 Oktober juga mencatat 1.800 baris kabupaten setelah dua impor. Hitungan DB saat ini belum diketahui. Jangan menjalankan impor lanjutan sebelum memilih sumber kanonik dan menyiapkan migrasi/deduplikasi data yang aman.
2. **P1: bootstrap Compose tidak menghasilkan aplikasi siap pakai.** `docker-entrypoint.sh` hanya `migrate`; Compose tidak punya service queue worker. `QUEUE_CONNECTION=database` dan submit pre-test dapat mendispatch `NilaiUlangJob` jika layanan hitung gagal. Tanpa worker, penilaian tertunda tidak pulih otomatis.
3. **P1: timer pre-test dapat diulang dengan reload.** `sisaDetik` selalu dibuat dari 60 menit saat komponen dimount, sedangkan tabel `pretest` tidak menyimpan tenggat dan service backend tidak menegakkan durasi. Ini memungkinkan pengerjaan lebih lama dari durasi yang ditampilkan.
4. **P1: jawaban terakhir dapat hilang saat submit.** View menunda autosave 800 ms, lalu `kumpulkan()` langsung mengubah status menjadi `MENGUMPULKAN`. Callback autosave yang belum jalan memeriksa status `MENGERJAKAN` dan melewati penyimpanan. Submit tidak menunggu request autosave yang sedang berjalan. Uji khusus perlu membuktikan jawaban terakhir tersimpan sebelum submit.
5. **P1: informasi offline menyesatkan.** UI mengatakan jawaban aman tersimpan lokal, tetapi jawaban hanya ada di memori Vue; localStorage menyimpan ID pre-test, bukan jawaban. Setelah reload saat offline, jawaban yang belum berhasil dikirim hilang. Saat koneksi pulih, UI hanya menjalankan timer visual “sinkron”, tanpa mengirim ulang jawaban gagal.
6. **P2: kegagalan daftar tingkat ditelan.** `muatTingkat()` menangkap error dan hanya mengatur `error=true`; pemanggil `siapkanAwal()` mengandalkan `catch` untuk menampilkan pesan. Kegagalan request dapat terlihat sebagai daftar tingkat kosong, bukan error.

## Task tiga tim

ID di bawah dapat langsung dipakai sebagai judul issue. Semua task mensyaratkan PR, bukti uji, dan pemeriksaan data sebelum perubahan DB bersama.

### Tim Backend Laravel

| ID | Prioritas | Task dan hasil terima |
| --- | --- | --- |
| BE-01 | P0 | Tetapkan sumber bank kanonik bersama DA, audit DB untuk `id_sumber` lama/baru serta referensi jawaban/pengerjaan, lalu buat migrasi/deduplikasi yang tidak menghapus soal yang sudah dipakai. Hasil terima: query sebelum/sesudah, jumlah unik, rollback, dan pre-test/latihan/simulasi tidak menampilkan soal sama dua kali dalam satu paket. Bergantung DA-01. |
| BE-02 | P1 | Buat bootstrap konten yang eksplisit dan portabel untuk Compose: mount folder pilihan, isi path container, jalankan seed/import secara aman atau sediakan perintah setup tunggal yang gagal keras bila konten wajib tak ada. Hasil terima: dari volume DB baru, setup menghasilkan soal kabupaten dan mencetak jumlah masuk/ditolak. Bergantung BE-01. |
| BE-03 | P1 | Tambahkan queue worker Compose dengan konfigurasi retry dan uji submit saat Python sementara gagal. Hasil terima: job tertunda diproses setelah Python pulih; status siswa beralih dari menilai ke hasil. |
| BE-04 | P1 | Simpan tenggat pre-test di server dan tolak/akhiri jawaban serta submit di luar tenggat secara konsisten. Hasil terima: reload, tab kedua, dan manipulasi jam klien tidak menambah waktu; kontrak API memberi sisa waktu/tenggat untuk FE. Koordinasi FE-01. |
| BE-05 | P2 | Putuskan cakupan auth: implementasi endpoint lupa/reset/ubah sandi untuk SPA atau tandai fitur belum tersedia bersama FE. Jika diimplementasikan, uji token kedaluwarsa dan pencabutan sesi. Tambahkan `activate` siswa dengan otorisasi dan test hanya jika kebijakan admin menyetujui reaktivasi. |

### Tim Frontend Vue

| ID | Prioritas | Task dan hasil terima |
| --- | --- | --- |
| FE-01 | P1 | Pakai tenggat dari BE untuk timer pre-test, termasuk resume dan reload. Hasil terima: sisa waktu sama sebelum dan sesudah reload, habis waktu mengunci pengerjaan sesuai respons server. Bergantung BE-04. |
| FE-02 | P1 | Flush autosave tertunda dan tunggu request simpan sebelum submit; tampilkan error bila simpan gagal. Hasil terima: mengetik jawaban terakhir lalu langsung klik Kumpulkan tidak kehilangan jawaban, termasuk jaringan lambat. |
| FE-03 | P1 | Betulkan alur offline: pilih antrean lokal yang benar-benar persisten dan tersinkron, atau hapus klaim penyimpanan lokal dan cegah submit sebelum semua jawaban tersimpan. Hasil terima: reload/offline/online punya status yang jujur; kegagalan sinkron tidak diberi pesan sukses. |
| FE-04 | P2 | Tampilkan error dan tombol coba lagi saat fetch tingkat gagal; jangan mengubah kegagalan menjadi daftar kosong. Hasil terima: test request gagal dan pulih. |
| FE-05 | P2 | Selesaikan keputusan auth bersama BE: hubungkan ke endpoint nyata atau sembunyikan form dan tombol palsu. Pulihkan/port suite e2e auth dari garis `main` lama atau buat smoke e2e yang menguji login, pre-test, dan error jaringan di monorepo. |

### Tim Data Analytics dan Konten

| ID | Prioritas | Task dan hasil terima |
| --- | --- | --- |
| DA-01 | P0 | Tetapkan `soal/impor/` atau `soal_osn/impor/` sebagai sumber kanonik. Buat manifest pemetaan ID dan laporan irisan teks (678 kabupaten, 438 provinsi) serta beda konten, kunci, pembahasan, gambar. Hasil terima: daftar keputusan per soal ambigu dan rekomendasi aman untuk BE-01. |
| DA-02 | P1 | Validasi paket kanonik: jumlah per tingkat/peruntukan/level/materi, referensi gambar, kunci, ID unik, kecukupan kuota pre-test/latihan/simulasi. Hasil terima: laporan yang dapat diregenerasi lewat satu perintah dan impor `--dry-run` nol penolakan setelah BE siap. |
| DA-03 | P2 | Setelah BE-01/02 selesai, siapkan impor provinsi dari paket kanonik dan bukti jumlah DB. Jangan memakai angka 734 bila memilih paket baru (814). Hasil terima: soal provinsi dapat dipilih, gambar tersedia, dan tidak bercampur duplikat lama. |
| DA-04 | P2 | Ganti kebijakan `peruntukan` berbasis tahun dengan manifest kurasi per soal atau nyatakan jelas sebagai label sementara sampai model `paket_tes` diputuskan. Selaraskan draft skema dengan paket dan `laporan.json` yang benar; putuskan soal tanpa label. |
| DA-05 | P3 | Tinjau `test_bobot.py`, `docs/a.txt`, dan artefak debug; pindahkan ke tempat yang tepat atau hapus lewat PR setelah isi dan pemiliknya dipastikan. |

Urutan lintas tim: **DA-01 → BE-01 → BE-02 → DA-03**. **BE-04 ↔ FE-01** perlu kontrak bersama. FE-02/03, BE-03, dan DA-02 dapat dimulai paralel. Gate rilis: test BE/FE/DA, uji HTTP dengan DB baru, lalu alur browser login → pre-test → penilaian → latihan → simulasi pada data kanonik.

## Usulan fitur kode referral per siswa

Fitur ini belum ada di kode. Untuk versi awal, setiap siswa memiliki satu kode acak yang unik dan stabil, misalnya `OSN7K4P9`. Kode dibuat di backend saat registrasi, bukan diturunkan dari ID, email, atau nama. Database memberi unique index. Akun siswa lama perlu diisi lewat backfill yang aman dijalankan ulang. Kode tetap tercatat jika akun dihapus lunak, supaya tautan lama tidak dialihkan ke pemilik baru.

Siswa dapat menyalin tautan `.../register?ref=OSN7K4P9`. FE mengisi kode secara otomatis atau menerima input manual; registrasi tetap dapat dilakukan tanpa kode. Backend memvalidasi kode, menolak referral ke diri sendiri, dan menyimpan `referred_by_user_id` sekali saja dalam transaksi registrasi. Jika kode tidak valid, tampilkan error pada field referral, jangan diam-diam membuangnya. Endpoint profil mengembalikan kode milik siswa; angka referral hanya menghitung pendaftaran sah. Hadiah, poin, dan syarat kelayakan belum ditentukan, sehingga jangan menambahkan kredit otomatis pada versi ini.

| Tim | ID | Task dan hasil terima |
| --- | --- | --- |
| Backend | BE-06 | Migrasi `users.referral_code` unik dan `users.referred_by_user_id` nullable, backfill akun siswa lama, generator kode dengan retry saat bentrok, validasi `referral_code` opsional pada `POST /api/auth/register`, respons profil berisi kode sendiri dan total referral. Test dua registrasi serentak, kode salah, referral diri sendiri, akun dinonaktifkan/dihapus, dan satu pendaftar hanya punya satu pengundang. |
| Frontend | FE-06 | Halaman profil menampilkan kode dan tombol salin tautan; halaman daftar membaca `?ref=`, menyediakan field referral opsional, dan meneruskan nilainya lewat `authService.register`. Test link valid, input manual, error kode, dan daftar tanpa kode. Bergantung BE-06. |
| Data Analytics | DA-06 | Definisikan laporan sederhana jumlah pendaftar per kode dan audit duplikasi/penyalahgunaan tanpa mengolah email atau identitas pribadi di laporan publik. Jika nanti ada hadiah, rumus kelayakan dan event pemicunya harus disepakati sebelum implementasi. Bergantung kontrak BE-06. |
