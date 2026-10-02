<?php

namespace App\Services;

use App\Contracts\Services\AturanServiceInterface;
use App\DTOs\AturanTingkat;

class AturanService implements AturanServiceInterface
{
    public function getAturan(int $tingkatSeleksiId): AturanTingkat
    {
        // Implementation stub
        return new AturanTingkat(
            tingkatSeleksiId: $tingkatSeleksiId,
            maxSiswa: 100,
            minNilai: 60.0,
        );
    }

    public function cekEligibilitas(int $siswaId, int $tingkatSeleksiId): bool
    {
        // Implementation stub
        return true;
    }
}
