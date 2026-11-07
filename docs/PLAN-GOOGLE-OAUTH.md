# Rencana Google OAuth — SIAP OSN

Status: **pengerjaan Task 1, 2, 3, dan 5 selesai secara lokal** (test backend
475/475 lulus + Pint bersih; frontend test unit 208/208 + lint + build lulus;
Task 2 menunggu verifikasi fisik server). Task 4, 6, dan butir tautan akun
di Task 5 menunggu push + deploy ke server. Cakupan hanya login/daftar dengan
Google untuk siswa. Login email dan password yang ada tetap tersedia. Kerjakan
task sesuai urutan; setiap kotak dicentang setelah hasilnya diverifikasi.

## Kondisi saat rencana dibuat

- Vue menyimpan Bearer token Sanctum dan memakai `/api/auth/login`,
  `/api/auth/register`, serta `/api/auth/me`. Google OAuth perlu berakhir dengan
  bentuk respons `{ user, token }` yang sama agar alur frontend tetap konsisten.
- Laravel berjalan di `https://api.mrcln0305.xyz`; frontend aktif di
  `https://mrcln0305.xyz`. Domain `siap-osn.id` belum dapat diakses saat
  diperiksa. Nilai aktif server ada di
  `/home/guntur/osn/src/.env.production`.
- `docker-compose.prod.yml` saat ini memaksa `SESSION_DRIVER=file`. Tabel
  `sessions` sudah memiliki migration, sehingga sesi OAuth dapat dipindah ke
  database agar tidak hilang saat container dibuat ulang.
- Tautan **Ketentuan Layanan** dan **Kebijakan Privasi** pada formulir daftar
  masih menuju `#agreement`. Halaman sebenarnya perlu dibuat sebelum rilis
  publik. [Kebijakan Google OAuth](https://developers.google.com/identity/protocols/oauth2/policies)

## Keputusan teknis

1. Laravel menangani authorization code flow memakai
   [Socialite](https://laravel.com/framework/docs/socialite). Mulai dari route
   `web` agar verifikasi `state` memakai sesi; jangan memakai `stateless()`.
2. Google hanya diminta cakupan identitas dasar `openid email profile`.
   Gunakan Google `sub` sebagai pengenal akun tetap dan periksa
   `email_verified`. Email tidak dipakai sebagai pengenal Google karena bisa
   berubah. [Referensi OpenID Connect Google](https://developers.google.com/identity/openid-connect/reference)
3. Akun Google baru mendapat role `siswa`. Akun admin, nonaktif, dan yang
   dihapus tidak masuk otomatis melalui Google. Akun lokal yang emailnya sama
   **tidak digabung diam-diam**; pemiliknya harus login biasa lalu menautkan
   Google dari profil. Ini juga menghindari klaim salah atas akun lokal yang
   emailnya belum pernah diverifikasi.
4. Backend callback tidak menaruh Bearer token Sanctum di URL. Backend membuat
   kode login acak, sekali pakai, berumur paling lama 60 detik; hanya hash kode
   disimpan di database. Browser diarahkan ke halaman frontend dengan kode di
   fragmen URL. Frontend segera menghapus fragmen dari riwayat dan menukar kode
   lewat API untuk memperoleh `{ user, token }`.
5. Frontend utama untuk alur ini adalah `https://mrcln0305.xyz`. Jangan menerima
   URL tujuan bebas dari query pengguna. Jika domain frontend lain perlu
   didukung, tambahkan daftar tujuan yang eksplisit.

Alur URL yang direncanakan:

```text
Vue -> GET https://api.mrcln0305.xyz/auth/google/redirect
    -> Google
    -> GET https://api.mrcln0305.xyz/auth/google/callback
    -> https://mrcln0305.xyz/oauth/google/selesai#code=<kode-sekali-pakai>
    -> POST https://api.mrcln0305.xyz/api/auth/google/exchange
    -> { user, token } -> dashboard siswa
```

## Task 1 — Siapkan Google Cloud dan halaman publik

- [ ] Tentukan pemilik Google Cloud project dan email kontak yang aktif.
- [x] Buat project Google `siap-osn` untuk produksi; keberadaannya terlihat
      dari OAuth client yang sudah dibuat.
- [ ] Gunakan project terpisah untuk pengembangan bila nanti diperlukan.
      [Kebijakan pemisahan lingkungan](https://developers.google.com/identity/protocols/oauth2/policies)
- [ ] Verifikasi kepemilikan domain `mrcln0305.xyz` di
      Google, lalu isi Branding dan Audience untuk aplikasi External.
- [x] Buat halaman Kebijakan Privasi dan Ketentuan Layanan yang benar-benar
      dapat dibuka tanpa login; ganti tautan `#agreement` di formulir daftar.
      Halaman `/kebijakan-privasi` dan `/ketentuan-layanan` sudah ada (rute
      publik tanpa login), tautan footer dan formulir daftar sudah diubah.
      Konten memakai pengelola Tim Dev PTI A UM 23, kontak
      siaposnptium@gmail.com, dan menyatakan siswa di bawah 18 tahun perlu
      persetujuan orang tua/wali. Verifikasi lokal: 200 test unit lulus,
      build lulus, kedua rute dibuka dengan 200 via preview. Mekanisme
      persetujuan orang tua di dalam aplikasi belum ada; pertanyaan tetap
      diarahkan ke email kontak. CATATAN DEPLOY: pastikan server produksi
      menyajikan fallback SPA agar deep link `https://mrcln0305.xyz/kebijakan-privasi`
      tidak 404 setelah rilis.
- [x] Buat OAuth client bertipe **Web application**. Daftarkan Authorized
      redirect URI persis:
      `https://api.mrcln0305.xyz/auth/google/callback` (tanpa `/` penutup).
      Google membandingkan URL ini secara persis.
      [Dokumentasi redirect URI](https://developers.google.com/identity/protocols/oauth2/web-server)
- [ ] Gunakan redirect melalui Laravel; untuk alur ini frontend tidak memakai
      Google JavaScript SDK, jadi Authorized JavaScript origins tidak diperlukan.
- [ ] Tambahkan akun uji pada Audience saat status aplikasi masih Testing;
      putuskan kapan aplikasi siap dipublikasikan.
- [ ] Simpan Client ID dan Client Secret di pengelola rahasia tim; jangan
      masukkan Client Secret ke repo, frontend, atau chat publik.

**Selesai bila:** aplikasi Google dapat memilih akun uji dan callback domain
terdaftar tanpa error `redirect_uri_mismatch`.

## Task 2 — Siapkan konfigurasi server

- [x] Pastikan `https://api.mrcln0305.xyz/auth/google/callback` diteruskan
      Cloudflare ke container backend. Tidak perlu membuka port baru; Compose
      sudah memetakan backend ke `127.0.0.1:8080`. Domain API merespons `/up`
      dengan 200; callback saat ini 404 karena route belum diimplementasikan.
- [x] Pastikan backend bisa melakukan DNS dan HTTPS keluar ke endpoint Google.
      Dokumen OpenID Google dapat diakses dari container dengan HTTP 200.
- [x] Tambahkan kredensial Google dan callback ke
      `/home/guntur/osn/src/.env.production` dengan izin file terbatas
      (`chmod 600`). Keberadaan tiga variabel dan izin file sudah diverifikasi:

      ```dotenv
      GOOGLE_CLIENT_ID=<client-id-dari-google>
      GOOGLE_CLIENT_SECRET=<client-secret-dari-google>
      GOOGLE_REDIRECT_URI=https://api.mrcln0305.xyz/auth/google/callback
      ```

- [x] Tambahkan `SESSION_SECURE_COOKIE=true` ke `.env.production` sebelum
      mengaktifkan alur OAuth.
- [x] Ubah override `SESSION_DRIVER` untuk backend pada
      `docker-compose.prod.yml` dari `file` ke `database`; migration tabel
      `sessions` sudah ada. Perubahan Compose sudah siap di lokal dan
      `SESSION_DRIVER=database` sudah tersimpan di env server, tetapi container
      aktif masih memakai override lama sampai perubahan di-push dan dideploy.
- [x] Biarkan cookie sesi hanya untuk host API (`SESSION_DOMAIN` kosong/tidak
      diset) dan `SameSite=Lax`. Verifikasi header `Set-Cookie` pada alur
      redirect dan callback HTTPS; periksa trusted proxy bila Laravel membaca
      request Cloudflare sebagai HTTP. STATUS: `SESSION_DOMAIN` dan
      `SameSite=Lax` sudah default config (`config/session.php`), keduanya
      tidak perlu diubah. `trustProxies` pada semua proxy (Cloudflare) kini
      diaktifkan di `bootstrap/app.php` agar Laravel membaca request sebagai
      HTTPS. Verifikasi header `Set-Cookie` fisik menunggu deploy.
- [x] Pastikan `APP_URL=https://api.mrcln0305.xyz` dan
      `FRONTEND_URL` memuat `https://mrcln0305.xyz`; keduanya sudah diverifikasi
      di env server. Periksa ulang setelah deploy.

**Selesai bila:** sesi yang dibuat pada route redirect masih terbaca pada
callback Google, termasuk setelah container backend dibuat ulang.

## Task 3 — Bangun backend Google OAuth

Status: **selesai di lokal; lulus `php artisan test` penuh (475 test) dan
Pint**. Semuanya menunggu push + deploy bersama perubahan Task 2.

- [x] Tambahkan `laravel/socialite` (v5.31) ke Composer dengan `composer.lock`
      yang ikut diperbarui.
- [x] Tambahkan config `google` di `config/services.php` dari tiga variabel
      `GOOGLE_*`; `GOOGLE_*` juga ditambahkan ke `.env.example`.
- [x] Migration `2026_10_28_000001`: kolom `google_sub` nullable pada `users`
      dengan partial unique index `users_google_sub_unique` (banyak NULL boleh;
      satu `sub` hanya melekat satu akun, termasuk akun terhapus), plus tabel
      `google_login_codes` (`user_id`, `kode_hash` unik, `kedaluwarsa_pada`,
      `dipakai_pada`). Hanya hash kode yang disimpan di database.
- [x] Penyimpanan kode sekali pakai: `GoogleLoginCodeRepository` membuat kode
      acak 64 karakter, menyimpan sha256-nya, umur 60 detik, membersihkan
      baris lama saat membuat kode baru, dan `konsumsi()` mengklaim atomik di
      dalam transaksi (lockForUpdate + update kondisional), jadi dua request
      serentak tidak bisa menukar kode yang sama.
- [x] `GET /auth/google/redirect` dan `GET /auth/google/callback` di
      `routes/web.php` dengan state sesi (`GoogleOAuthService` memakai
      Socialite tanpa `stateless()`). Penanganan: `?error=` dari Google
      (izin ditolak), `InvalidStateException`, kegagalan jaringan/token, dan
      identitas tidak lengkap; semua berakhir dengan pengalihan ke frontend
      bernama `/oauth/google/selesai#error=<kode>` (kode: `google_batal`,
      `google_state`, `google_gagal`, `google_taut`, `google_admin`,
      `google_nonaktif`, `google_email_verifikasi`). Tujuan frontend hanya
      dibaca dari `FRONTEND_URL`, bukan kueri pengguna.
- [x] Callback memvalidasi identitas: cari akun berdasarkan `google_sub`
      (login ulang), tolak Super Admin dan akun nonaktif; email sudah dipakai
      akun lokal tanpa tautan dibalas `google_taut` supaya pemiliknya login
      biasa dan menautkan sendiri; akun baru dibuat (role `siswa`, kata sandi
      acak yang tak pernah dikirim ke siapa pun, `email_verified_at` diisi
      hanya bila Google mengatakan email terverifikasi), lalu kode sekali
      pakai dibuat dan pengguna dialirkan ke frontend dengan `#code=`.
- [x] `POST /api/auth/google/exchange` dengan `throttle:5,1`, validasi
      FormRequest, konsumsi sekali pakai, dan respons `{ user, token }`
      seperti login API yang ada (`data.user.roles` berisi `siswa`).

- [x] Test backend dengan Socialite fake: redirect, izin ditolak, state
      tidak cocok, kegagalan layanan Google, siswa baru, akun lama tertaut,
      admin, nonaktif, email belum terverifikasi, kode kedaluwarsa, kode
      dipakai dua kali, dan validasi body. Sebanyak 15 test ada di
      `tests/Feature/Api/GoogleAuthTest.php`. (Bagian test dari Task 6
      untuk backend diserap di sini.)

**Selesai bila:** pengguna Google baru mendapat akun siswa dan satu token
Sanctum, sementara callback gagal tidak membuat akun atau token.

## Task 4 — Tautkan akun siswa lama

- [ ] Tambah aksi “Tautkan Google” di profil siswa yang sudah login.
- [ ] Buat endpoint API terautentikasi yang mengeluarkan niat tautan singkat;
      navigasi browser berikutnya membawa niat itu ke route redirect Google.
- [ ] Pada callback, pastikan niat tautan masih berlaku, Google belum tertaut
      ke akun lain, email Google terverifikasi dan cocok dengan email akun
      siswa yang login. Tolak admin dan akun nonaktif.
- [ ] Beri pesan yang jelas untuk email tidak cocok, akun Google sudah
      tertaut, niat kedaluwarsa, dan pengguna membatalkan izin.

**Selesai bila:** siswa lama dapat memakai Google pada login berikutnya tanpa
kehilangan progres, dan akun yang tidak cocok tidak tergabung.

## Task 5 — Hubungkan frontend Vue

- [x] Tambah tombol “Masuk dengan Google” pada halaman login dan daftar;
      tombol melakukan navigasi browser ke route redirect backend.
      LoginForm `masukGoogle()` dan RegisterForm `daftarGoogle()` panggil
      `urlRedirectGoogle()` (satu sumber di `lib/oauth.js`: origin API +
      `/auth/google/redirect`, tanpa `/api`).
- [x] Tambah route publik `/oauth/google/selesai` dengan keadaan memproses,
      sukses, dan gagal. Ambil kode dari fragmen URL, hapus fragmen segera,
      lalu POST ke endpoint exchange. View: `GoogleSelesaiView.vue`; route
      `oauth.google.selesai` bergabung dengan landing/kebijakan publik.
- [x] Setelah exchange berhasil, simpan token dan user melalui auth store
      yang ada, kosongkan data akun sebelumnya, lalu arahkan siswa ke dashboard.
      Aksi `masukDenganGoogle(kode)` pada auth store; pindah halaman lewat
      `dashboardFor(user.role)`.
- [x] Pastikan interceptor `401` tidak memicu redirect berulang ketika kode
      exchange tidak sah; tampilkan tindakan untuk mencoba login lagi.
      `/auth/google/exchange` masuk daftar `TANPA_REDIRECT_401` di `lib/api.js`;
      halaman gagal menawarkan "Coba Google lagi" dan "Masuk dengan email".
- [ ] Tambah tombol dan pesan tautan Google di profil siswa lama.
      Ditunda menunggu Task 4 (butuh deploy backend dulu).

**Selesai bila:** login Google tidak menampilkan token Sanctum di address bar
atau log navigasi, dan login email/password tetap berjalan.

## Task 6 — Test, CI/CD, dan rilis

- [ ] Test backend dengan Socialite fake: redirect, state salah, izin ditolak,
      siswa baru, akun lama, admin, nonaktif, email tidak terverifikasi,
      kode kedaluwarsa, dan kode dipakai dua kali.
- [ ] Test frontend untuk tombol, halaman callback, penyimpanan token,
      penghapusan fragmen URL, dan pesan gagal.
- [ ] Jalankan test Laravel, test/build frontend, serta build image produksi
      lewat `.github/workflows/ci-cd.yml`.
- [ ] Deploy migration dan konfigurasi server melalui alur branch `monorepo`.
      Jangan jalankan `migrate:fresh` pada database produksi.
- [ ] Uji manual dengan akun Google penguji: daftar baru, login ulang, akun
      siswa lama setelah tautan, pembatalan izin, dan logout.
- [ ] Cek bahwa akun admin tidak mendapat akses Google dan login password
      lama tetap berhasil.
- [ ] Periksa log backend tanpa mencatat authorization code, token Google,
      Client Secret, atau token Sanctum.

**Selesai bila:** seluruh job CI lulus dan alur penguji berhasil pada domain
publik sebelum Audience Google dipublikasikan lebih luas.
