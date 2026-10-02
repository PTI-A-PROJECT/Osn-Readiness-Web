<?php

namespace App\Contracts\Services;

interface SyaratSimulasiServiceInterface
{
    /**
     * Check if student meets simulasi prerequisites.
     */
    public function cekSyarat(int $siswaId, int $putaranId): bool;

    /**
     * Get list of unmet requirements.
     *
     * @return array<string>
     */
    public function getSyaratTidakTerpenuhi(int $siswaId, int $putaranId): array;

    /**
     * Check time slot availability.
     */
    public function cekSlotWaktu(int $siswaId, int $putaranId): bool;
}
