<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /**
     * Akun yang Google-nya tertaut pada `sub` ini. Satu Google hanya
     * boleh melekat pada satu akun (unique, termasuk akun terhapus).
     */
    public function findByGoogleSub(string $sub): ?User;

    /**
     * Lepaskan `sub` ini dari akun yang sudah dihapus dan kembalikan jumlah
     * baris yang diubah. Index unik google_sub tetap memegang akun terhapus,
     * jadi pemilik Google yang sama baru bisa mendaftar lagi setelah ini.
     */
    public function lepasGoogleDariAkunTerhapus(string $sub): int;

    /**
     * Pemilik satu kode referal. Akun yang sudah dihapus dianggap tidak
     * memiliki kode lagi, jadi kodenya tidak bisa dipakai mendaftar.
     */
    public function findByKodeReferal(string $kode): ?User;

    /**
     * Akun ber-role siswa, aktif maupun nonaktif, terbaru dulu. Akun admin
     * tidak termasuk.
     */
    public function paginasiSiswa(int $perPage = 15): LengthAwarePaginator;

    /**
     * Satu akun ber-role siswa; akun lain dianggap tidak ada.
     */
    public function cariSiswa(int $id): ?User;

    public function findByNim(string $nim): ?User;

    /**
     * Akun ber-role anggota, aktif maupun nonaktif, terbaru dulu.
     */
    public function paginasiAnggota(int $perPage = 15, ?string $urut = null): LengthAwarePaginator;

    public function kodeReferalDipakai(string $kode, int $kecualiUserId): bool;

    /**
     * @return Collection<int, int>
     */
    public function hitunganReferalAnggota(): Collection;
}
