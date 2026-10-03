<?php

namespace App\Services;

use App\Contracts\Services\SyaratSimulasiServiceInterface;

class SyaratSimulasiService implements SyaratSimulasiServiceInterface
{
    public function cekSyarat(int $siswaId, int $putaranId): bool
    {
        // Implementation stub
        return true;
    }

    /**
     * @return array<string>
     */
    public function getSyaratTidakTerpenuhi(int $siswaId, int $putaranId): array
    {
        // Implementation stub
        return [];
    }

    public function cekSlotWaktu(int $siswaId, int $putaranId): bool
    {
        // Implementation stub
        return true;
    }
}
