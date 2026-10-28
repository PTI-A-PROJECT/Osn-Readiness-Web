<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Exceptions\AkunTidakAktifException;
use App\Exceptions\KredensialTidakValidException;
use App\Models\User;
use App\Support\KodeReferal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function register(array $data, ?string $device = null): array
    {
        try {
            return DB::transaction(function () use ($data, $device): array {
                // Kode pengundang bukan milik pendaftar: diterjemahkan jadi
                // id pemilik kode lalu dibuang dari data, supaya create() di
                // bawah tidak menyimpan kode itu sebagai kode pendaftar sendiri.
                $kodePemberi = KodeReferal::normalisasi($data['kode_referal'] ?? null);
                unset($data['kode_referal']);

                $pemberi = null;

                if ($kodePemberi !== null) {
                    $pemberi = $this->userRepository->findByKodeReferal($kodePemberi);

                    // Validasi request sudah menolak kode tak dikenal; cek
                    // ulang di sini menutup jeda pemisah keduanya (mis.
                    // pemilik kode dihapus di antara keduanya).
                    if (! $pemberi instanceof User) {
                        throw ValidationException::withMessages([
                            'kode_referal' => ['Kode referal tidak ditemukan.'],
                        ]);
                    }
                }

                /** @var User $user */
                $user = $this->userRepository->create([
                    ...$data,
                    'is_active' => true,
                    'direferensikan_oleh' => $pemberi?->id,
                    // Register lewat form sudah memutuskan soal kode referal,
                    // jadi opsi input kode tidak pernah muncul untuk akun ini.
                    'referal_diisi_at' => now(),
                ]);

                $user->assignRole('siswa');

                return [
                    'user' => $user,
                    'token' => $this->buatToken($user, $device),
                ];
            });
        } catch (UniqueConstraintViolationException) {
            // Dua pendaftaran bersamaan dengan email yang sama sama-sama
            // lolos validasi; index users_email_aktif_unique menolak yang
            // kedua, dan ia dibalas seperti gagal validasi biasa.
            throw ValidationException::withMessages(['email' => ['Email sudah terdaftar.']]);
        }
    }

    public function login(string $email, string $password, ?string $device = null): array
    {
        $user = $this->userRepository->findByEmail($email);

        // Pesan sengaja sama untuk email tidak dikenal dan password salah,
        // supaya tidak membocorkan email mana yang terdaftar.
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new KredensialTidakValidException;
        }

        if (! $user->is_active) {
            throw new AkunTidakAktifException;
        }

        return [
            'user' => $user,
            'token' => $this->buatToken($user, $device),
        ];
    }

    public function logout(User $user): bool
    {
        $token = $user->currentAccessToken();

        // TransientToken (mis. actingAs di test) tidak punya baris database.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return true;
    }

    public function updateProfile(User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data): User {
                /** @var User $updated */
                $updated = $this->userRepository->update($user, [
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);

                return $updated;
            });
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan bersamaan dengan email yang sama sama-sama
            // lolos validasi; index users_email_aktif_unique menolak yang
            // kedua, dan ia dibalas seperti gagal validasi biasa.
            throw ValidationException::withMessages(['email' => ['Email sudah terdaftar.']]);
        }
    }

    private function buatToken(User $user, ?string $device): string
    {
        return $user->createToken($device ?? 'auth-token')->plainTextToken;
    }
}
