<?php

namespace App\Services;

use App\Contracts\Services\PutaranServiceInterface;
use App\DTOs\StatusPutaran;

class PutaranService implements PutaranServiceInterface
{
    public function getStatus(int $putaranId): StatusPutaran
    {
        // Implementation stub
        return StatusPutaran::BELUM_DIMULAI;
    }

    public function enroll(int $siswaId, int $putaranId): bool
    {
        // Implementation stub
        return true;
    }

    public function canEnroll(int $siswaId, int $putaranId): bool
    {
        // Implementation stub
        return true;
    }
}
