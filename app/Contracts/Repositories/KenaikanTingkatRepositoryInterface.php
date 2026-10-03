<?php

namespace App\Contracts\Repositories;

interface KenaikanTingkatRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Apakah siswa sudah lulus tingkat ini (satu baris status lulus).
     */
    public function adaLulus(int $userId, int $tingkatId): bool;
}
