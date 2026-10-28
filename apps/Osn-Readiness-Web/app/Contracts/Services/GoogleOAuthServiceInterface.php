<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

interface GoogleOAuthServiceInterface
{
    /**
     * AWAL alur OAuth: arahkan browser ke halaman izin Google dengan
     * `state` tersimpan di sesi (bukan stateless).
     */
    public function redirect(): RedirectResponse;

    /**
     * UJUNG alur OAuth: validasi identitas Google, siapkan/temukan akun siswa,
     * lalu alihkan ke frontend dengan kode sekali pakai di fragmen URL untuk
     * berjalan dengan sukses, atau kode kegagalan untuk anomali apa pun.
     */
    public function callback(Request $request): RedirectResponse;

    /**
     * Tukarkan kode sekali pakai yang dibawa frontend menjadi akun +
     * token Sanctum, bentuknya sama dengan login API yang ada.
     *
     * @return array{user: User, token: string}
     */
    public function tukarkanKode(string $kode, ?string $device = null): array;
}
