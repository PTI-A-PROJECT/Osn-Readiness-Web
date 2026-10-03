<?php

namespace App\Contracts\Services;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Daftarkan akun siswa baru beserta token pertamanya.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data, ?string $device = null): array;

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, ?string $device = null): array;

    /**
     * Hapus token yang sedang dipakai saja, bukan semua token user.
     */
    public function logout(User $user): bool;
}
