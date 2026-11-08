# Anggota: akun dari daftar NIM, login NIM, kode referal OSNNAMA, dan pantauan jumlah referal

## Context

Permintaan: cek docs soal referal untuk anggota; tambah role baru "anggota"; sediakan cara melihat berapa referal yang sudah masuk; 32 anggota (daftar NIM dan nama ada di bagian Verification) dibuat sebagai akun yang login memakai NIM dengan password NIM; kode referal anggota berformat `OSNNAMA`. Implementasi lokal saja: tanpa `git push`, tanpa PR, diuji di lokal.

Temuan docs dan kode (sudah dibaca):
- Satu-satunya bagian docs yang relevan: "Usulan fitur kode referral per siswa" di `docs/VERIFIKASI-AUDIT-DAN-TASK-3-TIM-27-OKT-2026.md` (BE-06, FE-06, DA-06). Docs melarang hadiah atau poin otomatis dan menyarankan laporan jumlah pendaftar per kode tanpa identitas pribadi. Role "anggota" tidak ada di docs maupun kode; role yang ada hanya `Super Admin` dan `siswa`.
- Sudah ada: `users.kode_referal` (`varchar(4)`, unik, tidak parsial, mencakup akun terhapus), `users.direferensikan_oleh`, relasi `User::diundang()` (akun terhapus tidak terhitung), `UserResource.jumlah_diundang` (benar hanya bila `loadCount('diundang')` dipanggil), tampilan kode dan jumlah di `ProfileView.vue` siswa.
- Kendala: `users.email` wajib diisi (`NOT NULL`, unik parsial `users_email_aktif_unique`); `ApiLoginRequest` mewajibkan `email` valid; form login memakai `<input type="email">`; limiter login dikunci pada `email|ip`; input kode referal di form daftar dibatasi `maxlength="4"`; rute siswa hanya dijaga `auth:sanctum` + `active` tanpa cek role.

Keputusan pengguna: (1) anggota khusus referal, tidak memakai fitur belajar; (2) anggota hanya dibuat lewat impor daftar NIM, tidak ada fitur menaikkan/menurunkan role siswa; (3) yang ditampilkan hanya jumlah referal, tanpa nama atau email pendaftar; (4) bagian "Nama" pada kode diambil dari kata pertama nama, pindah ke kata berikutnya bila kata itu sudah dipakai anggota lain, dan anggota boleh mengubah bagian nama itu secara bebas; (5) anggota punya dashboard dengan sidebar sendiri: menu Dashboard (jumlah referal sendiri, peringkat di antara semua anggota berupa angka saja tanpa nama atau jumlah anggota lain, total referal seluruh anggota) dan menu Kode Referal (kode, salin, ubah nama); (6) revisi: kode anggota tanpa sufiks acak, berbentuk `OSN<NAMA>` (contoh `OSNACHMAD`, tanpa tanda pisah); (7) revisi: leaderboard dapat dilihat semua akun yang login, termasuk anggota.

Hasil akhir: perintah `php artisan anggota:impor <file.csv>` membuat akun anggota; anggota login di form login yang sama dengan mengetik NIM dan NIM sebagai password, lalu mendarat di `/anggota` (Dashboard) dengan sidebar berisi Dashboard dan Kode Referal; halaman Kode Referal di `/anggota/kode` menampilkan kode, jumlah pendaftar, dan form ubah nama kode; Super Admin melihat tabel anggota (NIM, nama, kode, jumlah referal) di `/admin/anggota`; siswa bisa mengetik kode anggota saat daftar; backend menolak anggota di rute belajar, kecuali leaderboard yang terbuka untuk semua akun yang login.

Anggota diinput lewat `AnggotaSeeder` (daftar NIM dan nama di konstanta `ANGGOTA`; jalankan `php artisan db:seed --class=AnggotaSeeder --force`). Langkah setelah deploy di VPS production: [TUTORIAL-INPUT-ANGGOTA-BARU.md](TUTORIAL-INPUT-ANGGOTA-BARU.md).

## Approach

Kerjakan backend (B1 sampai B11) lalu frontend (F1 sampai F10); frontend bergantung pada kontrak API berikut. Tidak ada seeder berisi data anggota: daftar NIM berisi data pribadi dan password awal sama dengan NIM, jadi tidak boleh masuk repo; daftar dimasukkan lewat file CSV saat perintah dijalankan.

### Kontrak API

| Endpoint | Hasil |
| --- | --- |
| `POST /api/auth/login` body `{nim, password}` atau `{email, password}` | `200 {message, data: {user, token}}` seperti login yang ada; `nim` harus `^\d{8,20}$`; bila `nim` dan `email` sama-sama dikirim, `nim` dipakai; salah NIM atau password `401` dengan pesan yang sama; akun nonaktif `403`; keduanya kosong atau NIM bukan angka `422` |
| `GET /api/admin/anggota?page=&per_page=` | `200`, koleksi paginasi `UserResource` anggota (diurutkan nama), memuat `nim`, `name`, `kode_referal`, `jumlah_diundang`, `is_active`; `403` bila bukan Super Admin; `401` tanpa token |
| `PUT /api/anggota/kode-referal` body `{nama}` | `200 {message: "Kode referal diperbarui", data: UserResource}` dengan `kode_referal` = `OSN<NAMA>` (NAMA dibesarkan; tanpa sufiks); `422` pada `nama` bila tidak cocok `^[A-Za-z0-9]{2,16}$` atau kode hasil sudah dipakai akun lain; `403` bila bukan anggota |
| `GET /api/anggota/ringkasan` | `200 {message: "OK", data: {jumlah_referal, peringkat, jumlah_anggota, total_referal}}` (semuanya integer); `403` bila bukan anggota; `401` tanpa token |
| Rute belajar (tingkat, pretest, materi, quiz, simulasi, dashboard, riwayat) untuk anggota | `403 {message: "Akun anggota tidak dapat memakai fitur belajar."}` |
| `GET /api/auth/me` untuk anggota | `200` seperti biasa, memuat `kode_referal`, `jumlah_diundang`, `nim` |
| `GET /api/leaderboard?tingkat=` dan `GET /api/leaderboard/me?tingkat=` | `200` untuk semua akun yang login (siswa, anggota, Super Admin); `401` tanpa token. Untuk anggota, `/me` menjawab `layak: false` dengan `alasan` "Akun anggota tidak ikut peringkat latihan." tanpa memanggil service |

Definisi angka: `jumlah_diundang` = jumlah akun tidak terhapus dengan `direferensikan_oleh` = id anggota; akun nonaktif tetap terhitung (perilaku `diundang_count` yang sudah ada, tidak diubah).

Definisi ringkasan: himpunan anggota = akun berrole `anggota` yang tidak terhapus (akun nonaktif ikut). `jumlah_referal` = `diundang_count` anggota yang meminta. `peringkat` = 1 + jumlah anggota dengan `diundang_count` lebih besar (peringkat kompetisi: seri berbagi peringkat yang sama; anggota tanpa referal semuanya seri). `jumlah_anggota` = ukuran himpunan. `total_referal` = jumlah `diundang_count` seluruh anggota dalam himpunan (referal milik siswa biasa tidak terhitung).

Format kode anggota: `OSN<NAMA>`; NAMA huruf dan angka A-Z0-9 maksimal 16 karakter, tanpa tanda pisah dan tanpa sufiks acak; panjang maksimal 3+16 = 19. Karena tidak ada sufiks, keunikan bergantung pada NAMA: bila semua kata nama sudah menjadi kode orang lain, kata pertama disambung nomor urut (`OSNBUDI2`, `OSNBUDI3`). Huruf O tidak ada di `KodeReferal::SET_KARAKTER`, jadi kode acak siswa (4 karakter) tidak pernah bertabrakan dengan awalan `OSN`.

### Backend (`apps/Osn-Readiness-Web`)

**B0. Cabang lokal.** `git fetch origin monorepo && git switch -c feat/anggota-referral origin/monorepo`. Jangan `git add -A`, jangan commit, jangan push. Perubahan mode pada 11 file `.gitignore` di working tree adalah milik pengguna: biarkan.

**B1. Migrasi.** Buat `database/migrations/2026_11_08_000001_tambah_nim_dan_perlebar_kode_referal.php` (kelas anonim `extends Migration`, gaya sama dengan `2026_10_28_000001_add_google_sub_and_login_codes.php`):
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table): void {
        $table->string('nim', 20)->nullable();
    });
    // Unik hanya di antara akun yang belum dihapus, sama seperti users_email_aktif_unique.
    DB::statement('CREATE UNIQUE INDEX users_nim_aktif_unique ON users (nim) WHERE nim IS NOT NULL AND deleted_at IS NULL');
    // Kode anggota "OSN<NAMA>" (bisa lebih dari 4 karakter); indeks unik kode_referal tetap utuh.
    DB::statement('ALTER TABLE users ALTER COLUMN kode_referal TYPE VARCHAR(40)');
}

public function down(): void
{
    DB::statement('DROP INDEX IF EXISTS users_nim_aktif_unique');
    Schema::table('users', function (Blueprint $table): void {
        $table->dropColumn('nim');
    });
    // kode_referal sengaja tidak dikecilkan lagi: kode anggota akan terpotong.
}
```

**B2. Model dan resource.** `app/Models/User.php`: tambah `'nim'` ke daftar `#[Fillable([...])]`. `app/Http/Resources/UserResource.php`: tambah `'nim' => $this->nim,` tepat setelah `'email'`.

**B3. Role di seeder.** `database/seeders/RolesAndPermissionsSeeder.php`, setelah `Role::firstOrCreate(['name' => 'siswa', ...])` tambah `Role::firstOrCreate(['name' => 'anggota', 'guard_name' => 'web']);`. Tidak ada permission untuk anggota. Role juga dibuat idempoten oleh perintah impor (B7) karena deploy produksi hanya menjalankan `migrate --force`, bukan seeder.

**B4. Generator kode.** `app/Support/KodeReferal.php`: tambah `use Illuminate\Support\Str;`, konstanta `public const PREFIX_ANGGOTA = 'OSN';` dan `public const NAMA_MAKS = 16;`, konstanta privat `URUT_MAKS = 99`, serta method `kodeAnggota`, `kataNama`, `untukAnggota` dan helper privat `terpakai` (tidak ada padanan di kelas ini; `buatUnik()` yang ada hanya untuk kode siswa dan tidak diubah):
```php
/** Kata-kata nama dibersihkan menjadi A-Z0-9, dipotong NAMA_MAKS, kata kosong dibuang. @return list<string> */
public static function kataNama(string $nama): array
{
    $kata = [];
    foreach (preg_split('/\s+/', trim($nama)) ?: [] as $k) {
        $bersih = substr((string) preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($k))), 0, self::NAMA_MAKS);
        if ($bersih !== '') {
            $kata[] = $bersih;
        }
    }

    return $kata;
}

/** Kode anggota dari bagian nama: "budi" -> "OSNBUDI". */
public static function kodeAnggota(string $nama): string
{
    return self::PREFIX_ANGGOTA.strtoupper($nama);
}

/** OSN<NAMA>: kata pertama yang belum menjadi kode siapa pun; bila semua terpakai, kata pertama + nomor urut terkecil yang bebas (OSNBUDI2, OSNBUDI3, ...); bila tidak ada kata, "ANGGOTA". */
public static function untukAnggota(string $namaLengkap): string
{
    $kata = self::kataNama($namaLengkap) ?: ['ANGGOTA'];

    foreach ($kata as $k) {
        $kode = self::kodeAnggota($k);
        if (! self::terpakai($kode)) {
            return $kode;
        }
    }

    for ($urut = 2; $urut <= self::URUT_MAKS; $urut++) {
        $kode = self::kodeAnggota($kata[0].$urut);
        if (! self::terpakai($kode)) {
            return $kode;
        }
    }

    throw new RuntimeException('Gagal membuat kode referal unik.');
}

private static function terpakai(string $kode): bool
{
    return User::withTrashed()->where('kode_referal', $kode)->exists();
}
```
`withTrashed()` dipakai karena indeks unik `kode_referal` juga memegang akun terhapus. Perbarui docblock kelas (baris "Kode referal 4 karakter ...") dengan satu kalimat tentang format anggota.

**B5. Repository.** Tambah ke `app/Contracts/Repositories/UserRepositoryInterface.php` dan implementasi di `app/Repositories/Eloquent/UserRepository.php`:
```php
public function findByNim(string $nim): ?User
// $this->model->newQuery()->where('nim', $nim)->first();   (akun terhapus tidak ikut)

public function paginasiAnggota(int $perPage = 15): LengthAwarePaginator
// $this->model->newQuery()->whereHas('roles', fn ($q) => $q->where('name', 'anggota'))->with('roles')->withCount('diundang')->orderBy('name')->paginate($perPage);

public function kodeReferalDipakai(string $kode, int $kecualiUserId): bool
// $this->model->newQuery()->withTrashed()->where('kode_referal', $kode)->where('id', '!=', $kecualiUserId)->exists();

public function hitunganReferalAnggota(): Collection   // Illuminate\Support\Collection<int, int>: id anggota => jumlah referal; impor Collection di interface dan repository
// $this->model->newQuery()->whereHas('roles', fn ($q) => $q->where('name', 'anggota'))->withCount('diundang')->get()->mapWithKeys(fn (User $u): array => [$u->id => (int) $u->diundang_count]);
```
`paginasiAnggota` memakai `whereHas('roles', ...)`, bukan scope `->role('anggota')`: scope `role()` Spatie melempar `RoleDoesNotExist` bila baris role belum ada (kondisi produksi sebelum impor pertama). Jangan meniru `paginasiSiswa`.

**B6. Service.** Buat `app/Contracts/Services/AnggotaServiceInterface.php` dan `app/Services/AnggotaService.php` (konstruktor `private readonly UserRepositoryInterface $userRepository`):
- `getAll(int $perPage = 15): LengthAwarePaginator` memanggil `paginasiAnggota`.
- `impor(array $baris, bool $dryRun = false): array`; `$baris` adalah `list<array{nim: string, nama: string}>`; hasil `list<array{nim: string, nama: string, kode: ?string, status: 'dibuat'|'dilewati'}>`. Bila `! $dryRun`, `Role::findOrCreate('anggota', 'web')` dipanggil sekali di awal. Per baris: bila `findByNim($nim)` mengembalikan user, hasil `status => 'dilewati'` dengan `kode` miliknya dan tanpa perubahan apa pun (password dan kode tidak direset). Bila belum ada dan `$dryRun`, hasil `status => 'dibuat'`, `kode => null`. Bila belum ada dan bukan dry-run: `$user = $this->userRepository->create(['name' => $nama, 'email' => $nim.'@anggota.invalid', 'password' => $nim, 'is_active' => true, 'nim' => $nim, 'kode_referal' => KodeReferal::untukAnggota($nama), 'referal_diisi_at' => now()])`, lalu `$user->assignRole('anggota')`, hasil `status => 'dibuat'` dengan kode baru. Seluruh pemrosesan dalam satu `DB::transaction`. Password tidak di-hash manual: cast `hashed` pada `User` melakukannya (aturan repo: tidak ada `Hash::make` di service). `referal_diisi_at` diisi supaya akun ini tidak pernah diberi opsi input kode referal.
- `ubahNamaKode(User $anggota, string $nama): User`: `$kodeBaru = KodeReferal::kodeAnggota($nama)` (awalan `OSN` + nama dibesarkan); bila sama dengan kode sekarang, kembalikan tanpa perubahan; bila `kodeReferalDipakai($kodeBaru, $anggota->id)` atau `UniqueConstraintViolationException` saat `update($anggota, ['kode_referal' => $kodeBaru])`, `throw ValidationException::withMessages(['nama' => ['Kode referal itu sudah dipakai. Coba nama lain.']])`. Kembalikan `$anggota->load('roles')->loadCount('diundang')`.
- `ringkasan(User $anggota): array` (docblock `@return array{jumlah_referal: int, peringkat: int, jumlah_anggota: int, total_referal: int}`): `$hitungan = $this->userRepository->hitunganReferalAnggota(); $saya = (int) ($hitungan[$anggota->id] ?? 0);` kembalikan `['jumlah_referal' => $saya, 'peringkat' => 1 + $hitungan->filter(fn (int $n): bool => $n > $saya)->count(), 'jumlah_anggota' => $hitungan->count(), 'total_referal' => (int) $hitungan->sum()]`. Himpunan hanya puluhan akun, jadi dihitung di PHP, bukan SQL window function.
- Bind di `app/Providers/RepositoryServiceProvider.php`: tambah `use App\Contracts\Services\AnggotaServiceInterface;` dan `use App\Services\AnggotaService;` (urut alfabet), dan satu baris `$this->app->bind(AnggotaServiceInterface::class, AnggotaService::class);` setelah baris `bind(AuthServiceInterface::class, AuthService::class)`.

**B7. Perintah impor.** Buat `app/Console/Commands/ImporAnggotaCommand.php` (pola `ImporKontenCommand`: `extends Command`, `handle(AnggotaServiceInterface $anggota): int`, `self::SUCCESS`/`self::FAILURE`):
- `protected $signature = 'anggota:impor {file : Path file CSV dengan header nim,nama} {--dry-run : Laporkan tanpa menyimpan apa pun}';` `protected $description = 'Buat akun anggota (login dan password awal = NIM) dari file CSV';`
- Bila `! is_file($file)`: `$this->error("File tidak ditemukan: {$file}")` dan `FAILURE`.
- Baca dengan `fopen` + `fgetcsv`; buang BOM UTF-8 pada sel pertama; header (dipangkas, huruf kecil) harus persis `['nim', 'nama']`, kalau tidak `$this->error('Header harus nim,nama.')` dan `FAILURE`. Lewati baris yang seluruh selnya kosong.
- Validasi tiap baris, kumpulkan semua galat sebelum menyimpan apa pun: NIM harus cocok `^\d{8,20}$` (`Baris N: NIM tidak valid.`), nama setelah `trim` dan pemadatan spasi (`preg_replace('/\s+/', ' ', ...)`) tidak kosong (`Baris N: nama kosong.`), NIM tidak boleh muncul dua kali dalam file (`Baris N: NIM duplikat.`). Bila ada galat: cetak semuanya lewat `$this->error`, `FAILURE`, tidak ada yang tersimpan.
- Panggil `$anggota->impor($baris, (bool) $this->option('dry-run'))`, cetak `$this->table(['NIM', 'Nama', 'Kode referal', 'Status'], ...)` (kode `-` bila null), lalu `$this->info("{$dibuat} dibuat, {$dilewati} dilewati.")`; bila dry-run, tambah `$this->warn('DRY RUN: tidak ada yang disimpan.')`. Jangan pernah mencetak password. Laravel menemukan command di `app/Console/Commands` otomatis; tidak perlu registrasi.

**B8. Login NIM.**
- `app/Http/Requests/Auth/ApiLoginRequest.php`: di `prepareForValidation` tambah pemangkasan `nim` bila string; rules menjadi:
```php
'email' => ['required_without:nim', 'nullable', 'email'],
'nim' => ['required_without:email', 'nullable', 'string', 'regex:/^\d{8,20}$/'],
'password' => ['required', 'string'],
```
- `app/Contracts/Services/AuthServiceInterface.php`: tambah `public function loginDenganNim(string $nim, string $password, ?string $device = null): array;` (docblock `@return array{user: User, token: string}`). `app/Services/AuthService.php`: pindahkan badan `login()` (pencarian di luar) ke `private function masuk(?User $user, string $password, ?string $device): array` yang berisi pemeriksaan `! $user || ! Hash::check(...)` → `KredensialTidakValidException`, `! $user->is_active` → `AkunTidakAktifException`, dan pembuatan token; `login($email, ...)` menjadi `return $this->masuk($this->userRepository->findByEmail($email), $password, $device);` dan `loginDenganNim` serupa dengan `findByNim`. Pertahankan komentar "pesan sengaja sama" di `masuk`. Hanya `AuthController` memanggil `login()` (tes memanggil lewat HTTP), jadi tidak ada pemanggil lain yang berubah.
- `app/Http/Controllers/Api/AuthController.php` `login()`: `$nim = $request->validated('nim'); $hasil = $nim !== null ? $this->authService->loginDenganNim($nim, $request->validated('password')) : $this->authService->login($request->validated('email'), $request->validated('password'));` sisanya (respons `Login berhasil`) tidak berubah.
- `app/Providers/AppServiceProvider.php` limiter `login`: ganti `$email = Str::lower((string) $request->input('email'));` dengan `$identitas = Str::lower((string) ($request->input('nim') ?: $request->input('email')));` dan kunci `$identitas.'|'.$request->ip()`. Tanpa ini, semua login NIM dari satu jaringan kampus berbagi satu jatah 5 percobaan per menit karena kuncinya `'|'.$ip`.

**B9. Blok rute belajar untuk anggota.** Buat `app/Http/Middleware/TolakAnggota.php` (pola `CheckUserStatus`):
```php
public function handle(Request $request, Closure $next): Response
{
    if ($request->user()?->roles->contains('name', 'anggota')) {
        return response()->json(['message' => 'Akun anggota tidak dapat memakai fitur belajar.'], 403);
    }

    return $next($request);
}
```
`roles->contains` dipilih agar tidak bergantung pada ada-tidaknya baris role. `bootstrap/app.php`: tambah `use App\Http\Middleware\TolakAnggota;` dan alias `'bukan.anggota' => TolakAnggota::class` di `$middleware->alias([...])`. `routes/api.php`: bungkus blok dari komentar `// [B1-A] Akses tingkat dan status putaran.` sampai rute `riwayat` dengan `Route::middleware('bukan.anggota')->group(function (): void { ... });`. Grup `auth` (logout, me, profile, referal) dan grup `admin` tidak masuk pembungkus; rute `leaderboard` dan `leaderboard/me` juga sengaja di luar pembungkus (setelahnya) supaya semua akun yang login dapat membukanya.

**B10. Admin list, ringkasan, dan ubah nama kode.**
- `app/Policies/UserPolicy.php`: tambah `public function lihatAnggota(User $user): bool { return false; }` (Super Admin lolos lewat `before()`; `return false` agar tidak perlu baris permission).
- Buat `app/Http/Controllers/Api/Admin/AnggotaController.php` (tanpa kelas induk, mengikuti `SiswaController`): konstruktor `AnggotaServiceInterface`; `index()`: `Gate::authorize('lihatAnggota', User::class); $perPage = (int) request('per_page', 15); return UserResource::collection($this->anggotaService->getAll($perPage));`
- Buat `app/Http/Requests/Auth/UbahKodeReferalRequest.php` (FormRequest, `authorize()` true): `prepareForValidation` memangkas `nama`; rules `'nama' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,16}$/']`; `messages()`: `'nama.regex' => 'Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.'`.
- Buat `app/Http/Controllers/Api/AnggotaPanelController.php`: konstruktor `AnggotaServiceInterface`; `ringkasan(Request $request): JsonResponse` mengembalikan `response()->json(['message' => 'OK', 'data' => $this->anggotaService->ringkasan($request->user())])`; `ubahKode(UbahKodeReferalRequest $request): JsonResponse` memanggil `$this->anggotaService->ubahNamaKode($request->user(), $request->validated('nama'))` dan mengembalikan `response()->json(['message' => 'Kode referal diperbarui', 'data' => new UserResource($user)])`.
- `routes/api.php`: tambah `use App\Http\Controllers\Api\Admin\AnggotaController;` dan `use App\Http\Controllers\Api\AnggotaPanelController;` (urut alfabet). Di dalam grup `auth:sanctum` + `active`, tepat setelah grup `Route::prefix('auth')` dan sebelum blok `bukan.anggota`:
```php
Route::middleware('role:anggota')->prefix('anggota')->group(function (): void {
    Route::get('ringkasan', [AnggotaPanelController::class, 'ringkasan']);
    Route::put('kode-referal', [AnggotaPanelController::class, 'ubahKode']);
});
```
  Di grup `admin`, setelah `Route::post('siswa/{siswa}/deactivate', ...)`: `Route::get('anggota', [AnggotaController::class, 'index']);`.

**B11. Tes backend.** Semua memakai `RefreshDatabase` dan `$this->seed(RolesAndPermissionsSeeder::class)` di `setUp` (pola `SiswaAdminTest`). Anggota dibuat di tes dengan `User::factory()->create(['nim' => '230533610158', 'kode_referal' => 'OSNACHMAD'])` lalu `assignRole('anggota')`.
1. `tests/Feature/Console/ImporAnggotaTest.php` (file CSV sementara lewat `tempnam(sys_get_temp_dir(), 'anggota')`, dihapus di `tearDown`; `$this->artisan('anggota:impor', ['file' => $path])`):
   - dua baris `230533610158,ACHMAD ARIF SETIAWAN` dan `230533605308,ACHMAD IBRA ALFAHRO`: exit 0; dua user dengan `nim`, `name` apa adanya, `email` `<nim>@anggota.invalid`, `Hash::check($nim, $user->password)` benar, `is_active` true, `hasRole('anggota')`, `referal_diisi_at` terisi; kode pertama `OSNACHMAD` dan kedua `OSNIBRA` (kata pertama terpakai, pindah ke kata berikutnya).
   - tiga baris dengan nama `BUDI`: kode `OSNBUDI`, `OSNBUDI2`, `OSNBUDI3` (nomor urut bila semua kata terpakai).
   - kode milik akun terhapus (`OSNBUDI`) tidak dipakai ulang: anggota baru bernama `BUDI` mendapat `OSNBUDI2`.
   - jalankan dua kali: kali kedua exit 0, jumlah user tetap, hash password dan kode tidak berubah.
   - galat: NIM `abc`, header salah, NIM duplikat dalam file, dan file tidak ada masing-masing menghasilkan exit 1 dan nol user baru.
   - `--dry-run`: exit 0 dan nol user baru.
   - baris role `anggota` dihapus lebih dulu: impor tetap sukses dan user `hasRole('anggota')`.
2. `tests/Feature/Api/AnggotaLoginNimTest.php`: login `{nim, password: nim}` `200` dengan `data.user.roles.0` = `anggota`, `data.user.nim`, `data.token` string; password salah dan NIM tidak dikenal sama-sama `401` dengan `message` identik; akun nonaktif `403`; `nim` = `abc` dan body tanpa `nim` maupun `email` `422`; limiter: lima percobaan NIM A (password salah) lalu keenam `429`, sementara login NIM B yang benar dari IP yang sama tetap `200`.
3. `tests/Feature/Api/Admin/AnggotaAdminTest.php`: `GET /api/admin/anggota` sebagai Super Admin: anggota A (dua pendaftar aktif, satu nonaktif `is_active => false`, satu terhapus lewat `->delete()`, semuanya `direferensikan_oleh = A->id`) menunjukkan `jumlah_diundang` 3, anggota B 0; siswa tidak muncul; urut nama; sebagai siswa dan sebagai anggota `403`; tanpa login `401`; dengan baris role `anggota` dihapus `200` dan `data` kosong.
4. `tests/Feature/Api/AnggotaAksesTest.php`: sebagai anggota, `GET /api/dashboard` dan `GET /api/riwayat` `403` dengan pesan di kontrak; `GET /api/auth/me` `200` dengan `data.roles.0` = `anggota`, `data.kode_referal`, `data.jumlah_diundang`.
5. `tests/Feature/Api/AnggotaKodeReferalTest.php`: `PUT /api/anggota/kode-referal` `{nama: 'budi'}` `200` dan `data.kode_referal` = `OSNBUDI`; pendaftar yang sudah memakai kode lama tetap terhitung (`jumlah_diundang` tidak berubah); `POST /api/auth/register` dengan kode lama `422` pada `kode_referal`, dengan kode baru `201`; nama tidak valid (`a`, `dua kata`, `ab-cd`, 17 karakter) `422` pada `nama`; bentrok dengan anggota lain yang sudah memegang `OSNBUDI` `422` pada `nama`; nama sama dengan yang sekarang `200` tanpa perubahan; siswa `403`; tanpa login `401`.
6. `tests/Feature/Api/AnggotaRingkasanTest.php`: anggota A dengan 3 pendaftar yang dihitung (dua aktif, satu nonaktif; satu pendaftar terhapus lewat `->delete()` tidak dihitung), anggota B dan D dengan 1 pendaftar masing-masing, anggota C tanpa pendaftar, dan satu siswa biasa dengan 5 pendaftar (tidak masuk himpunan). `GET /api/anggota/ringkasan` sebagai A: `jumlah_referal` 3, `peringkat` 1, `jumlah_anggota` 4, `total_referal` 5. Sebagai B dan sebagai D: `peringkat` 2 (seri). Sebagai C: `jumlah_referal` 0 dan `peringkat` 4. Setelah D dihapus (`->delete()`), sebagai A: `jumlah_anggota` 3 dan `total_referal` 4. Sebagai siswa `403`; tanpa login `401`.
7. `tests/Feature/Api/LeaderboardAksesTest.php`: siswa, anggota, dan Super Admin `GET /api/leaderboard?tingkat=kabupaten` `200` dengan `data.tingkat` = `kabupaten`; anggota `GET /api/leaderboard/me?tingkat=kabupaten` `200` dengan `data.layak` `false` dan `data.alasan` "Akun anggota tidak ikut peringkat latihan."; tanpa login `401`.

### Frontend (`apps/siap-osn-fe/src`)

**F1. Endpoint.** `lib/endpoints.js`: di objek `admin` tambah `anggota: '/admin/anggota',`; tambah kunci tingkat atas `anggota: { ringkasan: '/anggota/ringkasan', kodeReferal: '/anggota/kode-referal' },` setelah blok `admin`.

**F2. `services/auth.js`.** `login`: ubah menjadi
```js
async login({ email, password }) {
  // "email" boleh berisi NIM (12 digit, 8 sampai 20 angka); NIM dikirim sebagai field `nim`.
  const id = String(email ?? '').trim()
  const body = /^\d{8,20}$/.test(id) ? { nim: id, password } : { email, password }
  const { user, token } = unwrap(await api.post(ENDPOINTS.auth.login, body))
  return { user: mapUser(user), token }
},
```
Kunci `email` dipertahankan (tidak diganti nama) supaya `stores/auth.js`, `LoginForm.vue`, dan tes yang ada tidak berubah. Fungsi khusus anggota tidak masuk `services/auth.js`; letaknya di `services/anggota.js` (F9).

**F3. `components/auth/LoginForm.vue`.** Input email (`id="email"`): ganti `type="email"` menjadi `type="text"`, `autocomplete="email"` menjadi `autocomplete="username"`, `placeholder` menjadi `nama@sekolah.sch.id atau NIM`, teks label `Email Aktif` menjadi `Email atau NIM`. Di `validasi()` ganti dua pesan: kosong `Email atau NIM wajib diisi`; format: `else if (!/^\d{8,20}$/.test(email.value) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value))` dengan pesan `Format email atau NIM tidak valid`. Pemanggilan `auth.login({ email: email.value, ... })` tidak berubah.

**F4. `components/auth/RegisterForm.vue`.** Input `id="referal"`: ubah `maxlength="4"` menjadi `maxlength="40"` (kode anggota sampai 21 karakter termasuk nomor urut; kolom database 40).

**F5. `services/mappers/user.js`.** Di `mapRole`, antara baris `Super Admin` dan `siswa`: `if (list.includes('anggota')) return 'anggota'`. Prioritas: `super_admin`, `anggota`, `siswa`. `mapUser` tidak diubah.

**F6. Admin data.** `services/mappers/admin.js` setelah `mapSiswa`:
```js
export function mapAnggota(r) {
  return {
    id: r.id,
    nama: r.name ?? '',
    nim: r.nim ?? '',
    kodeReferal: r.kode_referal ?? null,
    jumlahUndang: Number.isFinite(Number(r.jumlah_diundang)) ? Number(r.jumlah_diundang) : 0,
    aktif: Boolean(r.is_active),
  }
}
```
`services/admin.js`: impor `mapAnggota`; setelah `hapusSiswa` tambah
```js
async daftarAnggota({ halaman = 1, perPage = 15, signal } = {}) {
  const res = await api.get(ENDPOINTS.admin.anggota, { params: { page: halaman, per_page: perPage }, signal })
  const { items, ...paginasi } = halamanPaginasi(res, perPage, halaman)
  return { items: items.map(mapAnggota), ...paginasi }
},
```
`stores/admin.js`: tambah `ANGGOTA: 'anggota'` ke `MODUL`, tambah `MODUL.ANGGOTA` ke set `BERPAGINASI`, dan di `ambil()` tambah `case MODUL.ANGGOTA: return adminService.daftarAnggota({ halaman, perPage: perHalaman, signal })`.

**F7. Router dan menu.** `router/index.js`: impor lazy `const AnggotaLayout = () => import('@/layouts/AnggotaLayout.vue')`, `const AnggotaDashboardView = () => import('@/views/anggota/DashboardView.vue')`, `const AnggotaKodeView = () => import('@/views/anggota/KodeView.vue')`, dan `const AdminAnggotaView = () => import('@/views/admin/AnggotaView.vue')`; `dashboardFor(role)`: `super_admin` → `{ name: 'admin.dashboard' }`, `anggota` → `{ name: 'anggota.dashboard' }`, selain itu `{ name: 'siswa.dashboard' }`. Rute tingkat atas dengan layout sendiri (bukan `AppLayout`), ditaruh setelah rute `oauth.google.referal`:
```js
{
  path: '/anggota',
  component: AnggotaLayout,
  children: [
    { path: '', name: 'anggota.dashboard', component: AnggotaDashboardView, meta: { requiresAuth: true, role: 'anggota', title: 'Dashboard Anggota' } },
    { path: 'kode', name: 'anggota.kode', component: AnggotaKodeView, meta: { requiresAuth: true, role: 'anggota', title: 'Kode Referal' } },
  ],
},
```
  Anak `AppLayout`, setelah `admin/siswa`: `{ path: 'admin/anggota', name: 'admin.anggota', component: AdminAnggotaView, meta: { requiresAuth: true, role: 'super_admin', title: 'Kelola Anggota' } }`. `setupGuard` tidak diubah: `to.meta` menggabungkan meta seluruh rute yang cocok sehingga `requiresAuth` dan `role` milik anak terbaca, dan ketidakcocokan role mengarah ke `forbidden`. Anggota memakai layout sendiri karena `AppLayout` memanggil `progress.fetchDashboard()` untuk semua non-admin (403 bagi anggota) dan `UserMenu` menautkan ke profil siswa. `layouts/AppLayout.vue`: di `menuAdmin`, setelah `Manajemen Siswa`, tambah `{ kelompok: 'UTAMA', label: 'Manajemen Anggota', to: { name: 'admin.anggota' } },`.

**F8. Buat `views/admin/AnggotaView.vue` (tabel baca-saja).** Kerangka dari `views/admin/SiswaView.vue` tanpa filter, ekspor CSV, modal, dan konfirmasi: `MOD = MODUL.ANGGOTA`; `muat({ force })` memanggil `admin.muatDaftar(MOD, { force })` dengan galat `Gagal memuat daftar anggota.`; `AdminPageHeader` `judul="Manajemen Anggota"`, `deskripsi="Pantau jumlah pendaftar yang masuk lewat kode referal tiap anggota."`, slot `aksi-extra` berisi tombol "Muat ulang"; tabel dengan kolom NIM, Nama, Kode Referal (`a.kodeReferal || '-'`), Jumlah Referal (`a.jumlahUndang`); keadaan memuat `Memuat anggota…`; kosong `Belum ada anggota. Impor daftar anggota dengan perintah anggota:impor.`; `<AdminPaginasi :meta="meta" :memuat="admin.loading" @ganti="(h) => admin.keHalaman(MOD, h)" />`; `onMounted(() => muat({ force: true }))`.

**F9. Layanan, layout, dan halaman anggota.**
- Buat `services/mappers/anggota.js`:
```js
const angka = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0)

export function mapRingkasanAnggota(r) {
  return {
    jumlahReferal: angka(r?.jumlah_referal),
    peringkat: angka(r?.peringkat),
    jumlahAnggota: angka(r?.jumlah_anggota),
    totalReferal: angka(r?.total_referal),
  }
}
```
- Buat `services/anggota.js` (pola `services/auth.js`):
```js
import { api } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapUser } from '@/services/mappers/user.js'
import { mapRingkasanAnggota } from '@/services/mappers/anggota.js'
import { unwrap } from '@/services/envelope.js'

export const anggotaService = {
  // GET /anggota/ringkasan -> { message, data: { jumlah_referal, peringkat, jumlah_anggota, total_referal } }
  async ringkasan({ signal } = {}) {
    return mapRingkasanAnggota(unwrap(await api.get(ENDPOINTS.anggota.ringkasan, { signal })))
  },
  // PUT /anggota/kode-referal {nama} -> { message, data: user }
  async ubahKode(nama) {
    return mapUser(unwrap(await api.put(ENDPOINTS.anggota.kodeReferal, { nama })))
  },
}
```
- Buat `layouts/AnggotaLayout.vue`: kerangka `<div class="flex min-h-screen bg-[#f5f8fc]">`; sidebar `<aside class="sticky top-0 flex h-screen w-[200px] shrink-0 flex-col bg-[#0a1f3f] px-3 py-5 text-white">` meniru sidebar siswa di `layouts/AppLayout.vue`: logo `<img src="/logo-icon.png" alt="Logo SIAP OSN" class="h-7 w-7 shrink-0 rounded-full object-contain" />` dan teks `SIAP OSN`; `<nav aria-label="Menu anggota">` berisi dua `RouterLink` bertuliskan `Dashboard` (ke `{ name: 'anggota.dashboard' }`) dan `Kode Referal` (ke `{ name: 'anggota.kode' }`) dengan `exact-active-class="is-active"` dan kelas `nav-link ...` yang sama dengan sidebar siswa, tanpa ikon; di bawah (`mt-auto`) nama anggota (`auth.nama`) dan tombol `Keluar` (`await auth.logout(); router.push({ name: 'login' })`, sama dengan `UserMenu.keluar`); konten `<main class="min-w-0 flex-1 px-6 py-7"><RouterView /></main>`. Salin blok `<style scoped>` `.nav-link.is-active { background: rgb(240 179 35 / 0.16); color: #f0b323; font-weight: 600; }` dari `AppLayout.vue`. Layout ini tidak memanggil `progress.fetchDashboard()`.
- Buat `views/anggota/DashboardView.vue`: `auth = useAuthStore()`; `ringkasan = ref(null)`, `memuat = ref(true)`, `galat = ref(false)`; `muat()` mengatur `memuat = true`, `galat = false`, lalu `try { ringkasan.value = await anggotaService.ringkasan() } catch { galat.value = true } finally { memuat.value = false }`; `onMounted(muat)`. Tampilan: judul `Dashboard Anggota`, sapaan `Halo, {{ auth.nama }}.`; saat `memuat` teks `Memuat ringkasan…`; saat `galat` teks `Ringkasan belum bisa dimuat.` dan tombol `Coba lagi` (`@click="muat"`); selain itu tiga kartu (`rounded-2xl border border-[#e0e7f2] bg-white p-5`): `Referal Anda` bernilai `ringkasan.jumlahReferal`; `Peringkat Anda` bernilai `` `${ringkasan.peringkat} dari ${ringkasan.jumlahAnggota}` `` bila `ringkasan.jumlahReferal > 0`, selain itu `-`, dengan keterangan `Di antara semua anggota` atau `Belum ada referal`; `Total referal semua anggota` bernilai `ringkasan.totalReferal`. Bila `ringkasan.jumlahReferal === 0` tampil juga `Belum ada pendaftar lewat kode Anda. Bagikan kode dari menu Kode Referal.`
- Buat `views/anggota/KodeView.vue` (isi halaman saja; sidebar dan tombol Keluar disediakan layout): `auth = useAuthStore()`; `ref`: `kode` (awal `auth.user?.kodeReferal ?? ''`), `jumlah` (awal `auth.user?.jumlahUndang ?? 0`), `namaInput`, `galat`, `menyimpan`, `tersalin`, `tersimpan`. `AWALAN = 'OSN'`, `bagianNama = (k) => (String(k).startsWith(AWALAN) ? String(k).slice(AWALAN.length) : '')`; `namaInput` diisi `bagianNama(kode.value)` saat awal dan setiap `kode` berubah. `onMounted`: dalam `try/catch`, `const u = await authService.me()` lalu perbarui `kode`, `jumlah`, `namaInput`; bila gagal biarkan nilai terakhir tanpa pesan. `authService.me()` dipakai langsung, bukan `auth.fetchMe()`, karena `muatUser` di store mengosongkan user dan token pada kegagalan jaringan apa pun yang akan mengeluarkan anggota diam-diam.
- `simpan()` di `KodeView`: `galat = ''`, `tersimpan = false`, `menyimpan = true`; `try { const u = await anggotaService.ubahKode(namaInput.value.trim()); kode.value = u.kodeReferal ?? ''; jumlah.value = u.jumlahUndang; auth.user = u; tersimpan.value = true } catch (err) { galat.value = pesanField(err, 'nama') ?? pesanError(err, 'Nama kode belum bisa disimpan. Coba lagi.') } finally { menyimpan.value = false }`; impor `pesanError`, `pesanField` dari `@/lib/errors.js`. `salin()`: logika sama dengan `salinKode` di `views/siswa/ProfileView.vue` (`navigator.clipboard.writeText`, label `Tersalin` 2 detik).
- Teks `KodeView` (tanpa tanda pisah panjang): judul `Kode Referal`; label `Kode referal Anda`, kolom baca-saja berisi `kode || 'Belum tersedia'` dan tombol `Salin` (nonaktif bila kode kosong); baris `{{ jumlah }} pengguna sudah mendaftar lewat kode Anda`; bagian ubah: label `Nama pada kode`, `<input maxlength="16">`, bantuan `Huruf dan angka saja, 2 sampai 16 karakter. Kode menjadi OSN{{ namaInput.toUpperCase() }}.`, peringatan `Kode lama berhenti berlaku. Pendaftar yang sudah memakainya tetap terhitung.`, tombol `Simpan nama` (nonaktif saat `menyimpan`, saat input kosong, atau saat input sama dengan `bagianNama(kode)`), `<p role="alert">` untuk `galat`, `<p role="status">Kode diperbarui.</p>` saat `tersimpan`.

**F10. Tes frontend.**
- `services/mappers/user.spec.js`: `mapRole(['anggota'])` → `'anggota'`; `mapRole(['Super Admin', 'anggota'])` → `'super_admin'`; `mapRole(['anggota', 'siswa'])` → `'anggota'`.
- `services/mappers/admin.spec.js`: `mapAnggota` memetakan `nim`, `name`, `kode_referal`, `jumlah_diundang`, `is_active`; `jumlah_diundang` hilang atau bukan angka menjadi `0`.
- `services/admin.spec.js`: `daftarAnggota({ halaman: 2, perPage: 5 })` memanggil `api.get('/admin/anggota', { params: { page: 2, per_page: 5 }, signal: undefined })` dan memetakan item.
- `services/auth.spec.js`: `login({ email: '230533610158', password: '230533610158' })` memanggil `api.post('/auth/login', { nim: '230533610158', password: '230533610158' })`; login email biasa tetap `{ email, password }` (kasus yang ada tidak berubah).
- `router/guard.spec.js`: tambah `const anggota = { id: '3', nama: 'Ani', email: 'ani@example.com', role: 'anggota' }` dan kasus: anggota buka `/anggota` → `anggota.dashboard`; anggota buka `/anggota/kode` → `anggota.kode`; anggota buka `/siswa` → `forbidden`; anggota buka `/admin` → `forbidden`; siswa buka `/anggota` dan `/anggota/kode` → `forbidden`; anggota buka `/login` → `anggota.dashboard`; admin buka `/admin/anggota` → `admin.anggota`.
- Buat `components/auth/LoginForm.spec.js` (pola mock `views/GoogleSelesaiView.spec.js`: `vi.mock('@/router/index.js', () => ({ dashboardFor: vi.fn(() => '/x') }))` dan `vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: vi.fn() }) }))`, router memory, `vi.mock('@/services/auth.js', ...)`): NIM `230533610158` lolos validasi dan `authService.login` dipanggil dengan `{ email: '230533610158', password }`; teks `abc` menampilkan `Format email atau NIM tidak valid` dan `authService.login` tidak dipanggil; kosong menampilkan `Email atau NIM wajib diisi`; email valid tetap lolos.
- Buat `services/mappers/anggota.spec.js`: `mapRingkasanAnggota` memetakan keempat field; field hilang atau bukan angka menjadi `0`; `undefined` utuh menjadi semua `0`.
- Buat `services/anggota.spec.js` (pola `services/admin.spec.js`: `vi.mock('@/lib/api.js', () => ({ TOKEN_KEY: 'siap_osn_token', api: { get: vi.fn(), put: vi.fn() } }))`): `ringkasan()` memanggil `api.get('/anggota/ringkasan', { signal: undefined })` dan memetakan hasilnya; `ubahKode('BUDI')` memanggil `api.put('/anggota/kode-referal', { nama: 'BUDI' })` dan mengembalikan user terpetakan.
- Buat `views/anggota/DashboardView.spec.js` (mock `@/services/anggota.js`, pinia dengan user anggota): (a) `ringkasan` mengembalikan `{ jumlahReferal: 3, peringkat: 2, jumlahAnggota: 32, totalReferal: 41 }` → `3`, `2 dari 32`, dan `41` tampil; (b) `jumlahReferal: 0` → peringkat `-` dan kalimat `Belum ada pendaftar lewat kode Anda. Bagikan kode dari menu Kode Referal.` tampil; (c) `ringkasan` ditolak → `Ringkasan belum bisa dimuat.` tampil, klik `Coba lagi` memanggil `ringkasan` lagi dan angka tampil bila kali ini berhasil.
- Buat `views/anggota/KodeView.spec.js` (mock `@/services/auth.js` untuk `me` dan `@/services/anggota.js` untuk `ubahKode`): (a) `me` mengembalikan `{ kodeReferal: 'OSNACHMAD', jumlahUndang: 5 }` → kode dan `5 pengguna sudah mendaftar lewat kode Anda` tampil, input nama berisi `ACHMAD`; (b) `me` ditolak → nilai awal dari store tetap tampil dan `auth.user` tidak kosong; (c) isi `BEBAS` lalu `Simpan nama` memanggil `ubahKode('BEBAS')` dan menampilkan `OSNBEBAS`; (d) `ubahKode` ditolak 422 `{ response: { status: 422, data: { errors: { nama: ['Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.'] } } } }` → pesan itu tampil di `[role="alert"]`.
- Buat `layouts/AnggotaLayout.spec.js` (mock `@/services/auth.js`, router memory dengan rute `anggota.dashboard`, `anggota.kode`, `login`): tautan `Dashboard` dan `Kode Referal` ada; klik `Keluar` memanggil `authService.logout` dan router berpindah ke `login`.

## Critical files & anchors

- `apps/Osn-Readiness-Web/app/Support/KodeReferal.php`: `buatUnik()` untuk siswa jangan diubah; `untukAnggota()` memakai `withTrashed()` karena indeks unik `kode_referal` mencakup akun terhapus.
- `apps/Osn-Readiness-Web/app/Providers/AppServiceProvider.php` limiter `login` (~L32-36): kunci harus memakai NIM bila ada.
- `apps/Osn-Readiness-Web/routes/api.php` ~L53-131: tempat pembungkus `bukan.anggota` (blok `// [B1-A]` sampai `riwayat`; rute `leaderboard` di luar pembungkus), grup `anggota` (`ringkasan`, `kode-referal`), dan rute admin.
- `apps/Osn-Readiness-Web/app/Repositories/Eloquent/UserRepository.php`: `paginasiSiswa` memakai `->role('siswa')`; metode anggota harus memakai `whereHas('roles', ...)`.
- `apps/siap-osn-fe/src/components/auth/LoginForm.vue` (~L25-42 `validasi()`, ~L119-129 input email) dan `components/auth/RegisterForm.vue` (~L313 `maxlength`).

## Verification

Prasyarat: Docker berjalan; PHP dan Composer tidak ada di host. Frontend diuji dari salinan di `/tmp` karena `apps/siap-osn-fe/node_modules` milik root (EACCES) dan karena `bun run test:unit` memakai Node 26 yang `localStorage`-nya merusak jsdom (47 tes gagal palsu); pakai `bunx --bun vitest run`. Semua perintah di bawah sudah terbukti jalan di mesin ini.

**1. Tes frontend** (ulangi `rsync` setiap ada perubahan):
```
cd /home/aditya/projects/Osn-Readiness-Web
rsync -a --delete --exclude node_modules --exclude dist --exclude .env.local apps/siap-osn-fe/ /tmp/osn-fe/
cd /tmp/osn-fe && bun install --frozen-lockfile && bunx --bun vitest run && bunx oxlint src && bunx eslint src
```
Harapan: semua lulus (sebelum perubahan: 217) termasuk kasus F10; oxlint dan eslint tanpa temuan.

**2. Tes backend di Postgres asli:**
```
cd /home/aditya/projects/Osn-Readiness-Web
docker network create osn-test
docker run -d --name osn-test-pg --network osn-test -e POSTGRES_DB=osn_readiness_testing -e POSTGRES_USER=sail -e POSTGRES_PASSWORD=password postgres:16
mkdir -p /tmp/osn-ctx/docker && cp apps/Osn-Readiness-Web/Dockerfile /tmp/osn-ctx/ && cp apps/Osn-Readiness-Web/docker/docker-entrypoint.sh /tmp/osn-ctx/docker/
docker build -q -t osn-php-test /tmp/osn-ctx
rsync -a --exclude vendor --exclude node_modules --exclude 'storage/logs/*' --exclude 'storage/framework/testing' --exclude .env apps/Osn-Readiness-Web/ /tmp/osn-be/
docker run --rm --network osn-test --sysctl net.ipv6.conf.all.disable_ipv6=1 -v /tmp/osn-be:/var/www/html --entrypoint composer osn-php-test install --no-interaction --prefer-dist --no-progress --no-scripts
docker run --rm -v /tmp/osn-be:/var/www/html -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= --entrypoint php osn-php-test artisan package:discover --ansi
cp /tmp/osn-be/.env.example /tmp/osn-be/.env
```
Jalankan tes (ulangi `rsync` ke `/tmp/osn-be` sebelum tiap run):
```
docker run --rm --network osn-test --sysctl net.ipv6.conf.all.disable_ipv6=1 -v /tmp/osn-be:/var/www/html -e APP_ENV=testing -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_DEBUG=false -e DB_CONNECTION=pgsql -e DB_HOST=osn-test-pg -e DB_PORT=5432 -e DB_DATABASE=osn_readiness_testing -e DB_USERNAME=sail -e DB_PASSWORD=password -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=array -e MAIL_MAILER=array --entrypoint php osn-php-test artisan test --filter='ImporAnggotaTest|AnggotaLoginNimTest|AnggotaAdminTest|AnggotaAksesTest|AnggotaKodeReferalTest|AnggotaRingkasanTest'
```
Jebakan: tanpa `--sysctl net.ipv6.conf.all.disable_ipv6=1` Composer menggantung; `docker build` dari folder aplikasi penuh gagal pada `storage/framework/testing` milik root, karena itu konteks build minimal `/tmp/osn-ctx`.
Harapan: seluruh kasus B11 lulus. Lalu suite penuh (tanpa `--filter`) dan Pint:
```
docker run --rm -v /tmp/osn-be:/var/www/html --entrypoint php osn-php-test vendor/bin/pint --test app tests database
```
Harapan suite penuh: semua lulus kecuali 7 tes view Breeze dengan galat `Vite manifest not found` (kegagalan yang sama muncul di `origin/monorepo` tanpa perubahan ini karena CI menjalankan `npm run build` lebih dulu); kegagalan lain berarti regresi, terutama pada `AuthTest` (login email dan limiter) dan tes rute siswa (blok `bukan.anggota`). Pint: `PASS`.

**3. Bukti tes bermakna (mutation check).** Kembalikan sementara kunci limiter `AppServiceProvider` ke `$request->input('email')` saja: tes limiter di `AnggotaLoginNimTest` harus gagal (login NIM B ikut `429`). Hapus sementara pembungkus `bukan.anggota` di `routes/api.php`: kasus di `AnggotaAksesTest` harus gagal (`200`, bukan `403`). Pulihkan keduanya sesudahnya.

**4. Uji manual end-to-end di lokal, browser non-headless dan daftar anggota nyata.**
- Buat file daftar di luar repo, `/tmp/anggota.csv`:
```
nim,nama
230533610158,ACHMAD ARIF SETIAWAN
230533605308,ACHMAD IBRA ALFAHRO
230533608093,ADAM ABDILLAH MAHMUD
230533609786,ADEO MARCELINO
230533604609,ADINDA DWI KURNIA
230533609836,ADITYA EKA RAHMADANI
230533609321,AHMAD NABIL MUJADDID
230533610044,AHMAD SURYA ADI KUSUMA
230533600777,AISYA ALYANI SANTOSO
230533605824,ALI URAIDHI ASSAGAF
230533605510,AMALIA PRAMESWARI ALVINA
230533605618,AMELIA NOVELINDA
230533602149,ANANDA DHIAZ NIKOLA
230533606165,ANGGA RIZWAR
230533601886,ANISATUL QOMARIYAH
230533609139,ARFAN MIRZA RAMADANDI
230533601286,AULIA ELVIRA ADITAMA
230533610424,BIMO NUGROHO TARAQQI
230533609472,DAFFA RAMADHAN DAMAR PANULUH
230533610533,DAVINA ARAMITA NABILAH
230533607297,DEA ALENSA
230533607602,DEMIA ZAHARA AMANDASARI
230533608655,FAJAR PRAMUDYANA ANANTA PURBA
230533605506,FAKHRI RAFI` AHMADI
230533605767,FALIH HAIBAN FITRAHADI
230533608625,GUNTUR NAUFAL IMADUDDIN
230533605599,HARIS HAMMAN AL-ADL
230533609529,HILMI ZAKIN PUTRA
230533606674,IBNU PRASETIA NUGROHO
230533608035,JANUARTA MADI PRATAMA PUTRA
230533605312,MAHANAIM ELYANDA PUTRA KURNIAWAN KASIM
230533608811,MUHAMMAD HANIF ABDUR RAZAQ
```
- Backend: `docker exec osn-test-pg createdb -U sail osn_local`, lalu jalankan `artisan migrate --force` dan `artisan db:seed --class=` untuk `RolesAndPermissionsSeeder`, `TingkatSeleksiSeeder`, `AturanPemetaanSeeder`, `SuperAdminSeeder` (admin `admin@example.com` / `password`, default seeder), lalu server: `docker run -d --name osn-local-be --network osn-test --sysctl net.ipv6.conf.all.disable_ipv6=1 -p 8000:8000 -v /tmp/osn-be:/var/www/html -v /tmp/anggota.csv:/data/anggota.csv:ro -e APP_ENV=local -e APP_DEBUG=true -e APP_URL=http://localhost:8000 -e FRONTEND_URL=http://localhost:5173,http://127.0.0.1:5173 -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e DB_CONNECTION=pgsql -e DB_HOST=osn-test-pg -e DB_PORT=5432 -e DB_DATABASE=osn_local -e DB_USERNAME=sail -e DB_PASSWORD=password -e CACHE_STORE=file -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=file -e MAIL_MAILER=log --entrypoint php osn-php-test artisan serve --host=0.0.0.0 --port=8000 --no-reload` (semua perintah `artisan` lain memakai env yang sama lewat `docker run --rm`). (cache file dipakai agar limiter bernama bertahan antar-request pada `artisan serve`).
- Impor: `artisan anggota:impor /data/anggota.csv --dry-run` mencetak 32 baris `dibuat` dan `DRY RUN`, nol user bertambah; `artisan anggota:impor /data/anggota.csv` mencetak `32 dibuat, 0 dilewati`; menjalankannya lagi mencetak `0 dibuat, 32 dilewati`. Cek kode: `docker exec osn-test-pg psql -U sail -d osn_local -c "select nim, name, kode_referal from users where nim is not null order by nim"`: `ACHMAD ARIF SETIAWAN` berkode `OSNACHMAD`, `ACHMAD IBRA ALFAHRO` berkode `OSNIBRA`, `AHMAD NABIL MUJADDID` berkode `OSNAHMAD`, `AHMAD SURYA ADI KUSUMA` berkode `OSNSURYA`, `HARIS HAMMAN AL-ADL` berkode `OSNHARIS`.
- Frontend: salinan `/tmp/osn-fe` dengan `.env.local` berisi `VITE_API_BASE_URL=http://localhost:8000/api`, jalankan `bun run dev -- --host localhost` (port 5173).
- Browser: `browser.open({ app: { path: '/home/aditya/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome', args: ['--user-data-dir=/tmp/osn-visible-profile', '--no-first-run', '--window-size=1280,800'] }, headed: true, persist: true })`. Selektor: form masuk `#email` (kini menerima NIM) dan `#password`; form daftar `#nama`, `#email`, `#password`, `#konfirmasi`, `#referal`, `#agreement`.
- Skenario dan harapan:
  1. `/login`, NIM `230533610158` dan password `230533610158` → mendarat di `/anggota` (bukan `/siswa`) dengan sidebar berisi `Dashboard` dan `Kode Referal`. Dashboard menampilkan `Referal Anda` `0`, `Peringkat Anda` `-` (keterangan `Belum ada referal`), `Total referal semua anggota` `0`, dan kalimat `Belum ada pendaftar lewat kode Anda. Bagikan kode dari menu Kode Referal.` Klik `Kode Referal` → `/anggota/kode` menampilkan `OSNACHMAD`, `0 pengguna sudah mendaftar lewat kode Anda`, input nama berisi `ACHMAD`.
  2. Keluar lewat tombol di sidebar. `/register`: daftar siswa S1 mengetik kode `OSNACHMAD` penuh di kolom referal (tidak terpotong) → auto login ke `/siswa`. Keluar, masuk lagi sebagai anggota → Dashboard `Referal Anda` `1`, `Peringkat Anda` `1 dari 32`, `Total` `1`; halaman Kode Referal `1 pengguna ...`.
  3. Di halaman Kode Referal isi nama `BEBAS`, `Simpan nama` → kode menjadi `OSNBEBAS`, tampil `Kode diperbarui.`. Mengisi `dua kata` → pesan `Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.`.
  4. Daftar siswa S2 dengan kode lama `OSNACHMAD` → galat `Kode referal tidak ditemukan.`; dengan `OSNBEBAS` → berhasil. Kembali sebagai anggota → Dashboard `Referal Anda` `2`, `Peringkat Anda` `1 dari 32`, `Total` `2` (S1 tetap terhitung).
  5. Daftar siswa S3 dengan kode anggota kedua (`230533605308`, kodenya `OSNIBRA`, dibaca dari halaman Kode Referal anggota itu atau dari tabel admin). Masuk sebagai `230533605308` / `230533605308` → Dashboard `Referal Anda` `1`, `Peringkat Anda` `2 dari 32`, `Total` `3`.
  6. Sebagai anggota buka `/siswa` → halaman Akses Ditolak. `curl -s -H "Authorization: Bearer <token>" http://localhost:8000/api/dashboard` (token dari `POST /api/auth/login` dengan `{nim, password}`) → `403` dengan `{"message":"Akun anggota tidak dapat memakai fitur belajar."}`; `curl -s -H "Authorization: Bearer <token>" http://localhost:8000/api/anggota/ringkasan` → `200` dengan `data` berisi `jumlah_referal`, `peringkat`, `jumlah_anggota` 32, `total_referal`; `curl -s -H "Authorization: Bearer <token>" "http://localhost:8000/api/leaderboard?tingkat=kabupaten"` → `200` (leaderboard terbuka untuk anggota).
  7. Login siswa S1 dengan email dan password biasa masih berhasil ke `/siswa`; siswa S1 membuka `/anggota` → halaman Akses Ditolak.
  8. Masuk sebagai admin → `/admin/anggota`: 32 anggota terbagi 3 halaman (15 per halaman), urut nama; baris Achmad Arif Setiawan menunjukkan kode `OSNBEBAS` dan Jumlah Referal `2`; menu "Manajemen Anggota" ada di sidebar.
  9. Limiter: enam kali `POST /api/auth/login` `{nim: "230533608093", password: "salah"}` → percobaan keenam `429`; `{nim: "230533609786", password: "230533609786"}` pada waktu yang sama → `200`.
- Bersihkan: hentikan server frontend; `docker rm -f osn-local-be osn-test-pg`; `docker network rm osn-test`; `docker rmi osn-php-test`; hapus `/tmp/osn-fe /tmp/osn-ctx /tmp/osn-visible-profile /tmp/anggota.csv`; hapus `/tmp/osn-be` lewat `docker run --rm -v /tmp:/t --entrypoint rm postgres:16 -rf /t/osn-be` (isi `vendor` milik root); pastikan `git status` hanya menampilkan file yang diubah rencana ini plus 11 `.gitignore` bawaan dan tidak ada file CSV di repo.

## Assumptions & contingencies

- Asumsi (bisa diubah pengguna): password awal anggota sama dengan NIM sesuai permintaan. Aplikasi belum punya fitur ganti password, jadi anggota tidak bisa menggantinya dan NIM bukan rahasia; dampaknya terbatas karena akun anggota hanya melihat kode dan jumlah referalnya.
- Asumsi: kolom `email` tetap wajib, jadi anggota diberi email penanda `<nim>@anggota.invalid` (domain `.invalid` tidak pernah bisa menjadi email nyata). Bila data email asli anggota diperlukan, ubah kolom `users.email` menjadi nullable dan sesuaikan `UserResource`, `UpdateProfileRequest`, dan `mapUser`.
- Asumsi: "nama pertama sudah" ditafsirkan sebagai kata pertama nama sudah dipakai di kode anggota lain (termasuk akun terhapus); kata kedua dicoba, lalu ketiga dst., dan bila semua terpakai kata pertama dipakai lagi (keunikan dijamin nomor urut: `OSNBUDI2`, `OSNBUDI3`). Nama tersimpan sesuai isi file (huruf besar).
- Asumsi: anggota boleh mengubah bagian nama menjadi huruf/angka 2 sampai 16 karakter, tanpa batas frekuensi; kode hasil yang sudah dipakai akun lain ditolak (422). Mengubah nama membuat kode lama tidak berlaku; pendaftar yang sudah tercatat tidak terpengaruh karena terhubung lewat id akun.
- Asumsi: kode referal siswa tetap 4 karakter acak; hanya anggota yang berformat `OSN<NAMA>`.
- Asumsi: peringkat anggota dihitung sebagai peringkat kompetisi atas semua anggota tidak terhapus (termasuk yang nonaktif); seri berbagi peringkat yang sama, dan peringkat disembunyikan (`-`) selama jumlah referal anggota itu 0. Dashboard hanya menampilkan angka peringkat dan total, tanpa nama atau jumlah anggota lain.
- Asumsi: hasil kerja dibiarkan tidak di-commit di cabang lokal `feat/anggota-referral`; tidak ada push, PR, atau perubahan produksi. Menjalankan `anggota:impor` di produksi nanti adalah langkah terpisah dengan file CSV yang disimpan di luar git.
- Kontingensi: bila `ALTER COLUMN kode_referal TYPE VARCHAR(40)` ditolak oleh dependensi indeks, jalankan `DROP INDEX` pada indeks unik `kode_referal`, ubah tipe, lalu buat ulang indeks unik dengan nama yang sama di migrasi yang sama (cek nama dengan `\d users` di psql).
- Kontingensi: `ImporKontenCommand` tidak didaftarkan manual di mana pun (`grep -rn "ImporKontenCommand" apps/Osn-Readiness-Web` hanya menemukan kelasnya), jadi Laravel menemukan command di `app/Console/Commands` otomatis dan `ImporAnggotaCommand` tidak perlu registrasi. Bila `php artisan list` tetap tidak memuat `anggota:impor`, daftarkan lewat `->withCommands([ImporAnggotaCommand::class])` pada rantai `Application::configure(...)` di `bootstrap/app.php`.
- Kontingensi: bila composer install di Docker menggantung, pastikan `--sysctl net.ipv6.conf.all.disable_ipv6=1` ada pada perintah `docker run`.
