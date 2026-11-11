<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnggotaServiceInterface
{
    public function getAll(int $perPage = 15, ?string $urut = null): LengthAwarePaginator;

    /**
     * @param  list<array{nim: string, nama: string}>  $baris
     * @return list<array{nim: string, nama: string, kode: ?string, status: 'dibuat'|'dilewati'}>
     */
    public function impor(array $baris, bool $dryRun = false): array;

    public function ubahNamaKode(User $anggota, string $nama): User;

    /**
     * @return array{jumlah_referal: int, peringkat: int, jumlah_anggota: int, total_referal: int}
     */
    public function ringkasan(User $anggota): array;
}
