<?php

namespace App\Services;

use App\Contracts\Services\PenilaianServiceInterface;

class PenilaianService implements PenilaianServiceInterface
{
    public function nilaiBerjalan(int $simulasiId, int $siswaId): void
    {
        // Implementation stub
    }

    public function finalizeNilai(int $simulasiId): array
    {
        // Implementation stub
        return [];
    }

    public function getNilai(int $simulasiId): array
    {
        // Implementation stub
        return [];
    }
}
