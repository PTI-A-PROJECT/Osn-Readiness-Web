<?php

namespace App\Contracts\Services;

interface KelulusanServiceInterface
{
    /**
     * Check if student has passed based on criteria.
     */
    public function cekKelulusan(int $siswaId, int $tingkatSeleksiId): bool;

    /**
     * Get graduation status details.
     *
     * @return array{lulus: bool, nilai: float, keterangan: string}
     */
    public function getDetailKelulusan(int $siswaId, int $tingkatSeleksiId): array;
}
