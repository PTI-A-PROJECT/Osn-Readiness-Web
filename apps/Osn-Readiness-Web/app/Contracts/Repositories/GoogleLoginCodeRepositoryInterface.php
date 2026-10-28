<?php

namespace App\Contracts\Repositories;

use App\Models\GoogleLoginCode;
use App\Models\User;

interface GoogleLoginCodeRepositoryInterface
{
    /**
     * Buat kode sekali pakai untuk user. Hanya hash yang disimpan di
     * database; kode mentah dikembalikan sekali untuk dikirim ke browser.
     *
     * @return string kode mentah
     */
    public function buat(User $user): string;

    /**
     * Ambil juga menghabiskan kode secara atomik: dua request yang bersaing
     * tidak boleh sama-sama mendapatkan kode. Kode dipakai, kedaluwarsa,
     * atau tidak dikenal menghasilkan null. Baris lama (terpakai/kedaluwarsa)
     * ikut dibersihkan mengikuti konsumsi yang berhasil.
     */
    public function konsumsi(string $kodeHash): ?GoogleLoginCode;
}
