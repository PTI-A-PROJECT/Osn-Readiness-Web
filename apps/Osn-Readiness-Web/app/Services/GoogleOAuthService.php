<?php

namespace App\Services;

use App\Contracts\Repositories\GoogleLoginCodeRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\GoogleOAuthServiceInterface;
use App\Exceptions\AkunTidakAktifException;
use App\Exceptions\GoogleAkunAdminException;
use App\Exceptions\KodeGoogleTidakValidException;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleOAuthService implements GoogleOAuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly GoogleLoginCodeRepositoryInterface $kodeLogin,
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        // Google mengirim ?error= saat pengguna menolak izin; tidak ada
        // progres yang bisa dilakukan, cukup laporkan kegagalannya.
        if ($request->query('error')) {
            return $this->alihkanKeGagal('google_batal');
        }

        try {
            $identitas = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->alihkanKeGagal('google_state');
        } catch (GuzzleException) {
            // Google tidak bisa dihubungi atau responsnya rusak.
            return $this->alihkanKeGagal('google_gagal');
        } catch (\Throwable) {
            // Token ditukar gagal, ID token tidak sah, dll.
            return $this->alihkanKeGagal('google_gagal');
        }

        $sub = (string) ($identitas->id ?? '');
        $email = strtolower((string) ($identitas->email ?? ''));
        $emailTerverifikasi = (bool) ($identitas->user['email_verified'] ?? false);

        // Cakupan yang diminta hanya openid email profile, jadi nilai
        // selengkap di atas boleh diharapkan ada. Tanpa salah satu, identitas
        // tidak cukup untuk membentuk akun dan alur dihentikan.
        if ($sub === '' || $email === '') {
            return $this->alihkanKeGagal('google_gagal');
        }

        // 1. Google yang sudah tertaut: inilah login biasa.
        $tertaut = $this->userRepository->findByGoogleSub($sub);
        if ($tertaut) {
            if (! $tertaut->is_active) {
                return $this->alihkanKeGagal('google_nonaktif');
            }

            if ($tertaut->hasRole('Super Admin')) {
                return $this->alihkanKeGagal('google_admin');
            }

            return $this->alihkanKeSukses($tertaut);
        }

        // 2. Email sudah dipakai akun lokal: jangan gabung diam-diam.
        //    Pemilik akun login biasa lalu menautkan Google dari profil.
        $konflikEmail = $this->userRepository->findByEmail($email);
        if ($konflikEmail) {
            return $this->alihkanKeGagal('google_taut');
        }

        // 3. Google wajib menandai email terverifikasi sebelum akun baru
        //    dibuat, agar tidak ada akun yang dibuat mengatasnamakan email
        //            yang belum terbukti milik pendaftar.
        if (! $emailTerverifikasi) {
            return $this->alihkanKeGagal('google_email_verifikasi');
        }

        return $this->alihkanKeSukses($this->buatSiswa($sub, $email, $identitas));
    }

    public function tukarkanKode(string $kode, ?string $device = null): array
    {
        $baris = $this->kodeLogin->konsumsi(hash('sha256', $kode));
        if (! $baris) {
            throw new KodeGoogleTidakValidException;
        }

        $user = $baris->user;

        if (! $user->is_active) {
            throw new AkunTidakAktifException;
        }

        if ($user->hasRole('Super Admin')) {
            throw new GoogleAkunAdminException;
        }

        return [
            'user' => $user,
            'token' => $user->createToken($device ?? 'auth-token')->plainTextToken,
        ];
    }

    private function buatSiswa(string $sub, string $email, mixed $identitas): User
    {
        $verified = (bool) ($identitas->user['email_verified'] ?? false);

        return DB::transaction(function () use ($sub, $email, $identitas, $verified): User {
            $user = $this->userRepository->create([
                // Nama akar untuk nama email dipakai bila Google tidak
                // mengirim nama profil sama sekali.
                'name' => filled($identitas->name) ? $identitas->name : Str::before($email, '@'),
                'email' => $email,
                // Kata sandi acak yang tidak pernah dikirim ke siapa pun.
                // Kata sandi tidak lagi menjadi cara masuk akun Google ini.
                'password' => Str::random(40),
                'is_active' => true,
                'email_verified_at' => $verified ? now() : null,
                'google_sub' => $sub,
            ]);

            $user->assignRole('siswa');

            return $user;
        });
    }

    private function alihkanKeSukses(User $user): RedirectResponse
    {
        // Kode dikirim lewat fragmen (#) supaya tidak masuk log akses server
        // (fragmen tidak dikirim browser ke server), sekali pakai, dan
        // ditukar frontend lewat /api/auth/google/exchange.
        $kode = $this->kodeLogin->buat($user);

        return redirect()->away($this->tujuanFrontend().'/oauth/google/selesai#code='.$kode);
    }

    private function alihkanKeGagal(string $kodeKegagalan): RedirectResponse
    {
        return redirect()->away($this->tujuanFrontend().'/oauth/google/selesai#error='.$kodeKegagalan);
    }

    /**
     * Tujuan frontend diambil dari env (FRONTEND_URL), bukan dari kueri
     * pengguna, supaya tidak ada pengalihan terbuka dari mana-mana ke mana
     * saja pada callback. Bila env berisi daftar domain dipisah koma,
     * hanya tujuan pertama yang dipakai.
     */
    private function tujuanFrontend(): string
    {
        $daftar = (string) config('services.google.frontend_url', '');
        $pertama = trim((string) (explode(',', $daftar)[0] ?? ''));

        return rtrim($pertama, '/');
    }
}
