<?php

namespace App\Services;

use App\Contracts\Services\KelulusanServiceInterface;

class KelulusanService implements KelulusanServiceInterface
{
    public function cekKelulusan(int $siswaId, int $tingkatSeleksiId): bool
    {
        // Implementation stub
        return false;
    }

    /**
     * @return array{lulus: bool, nilai: float, keterangan: string}
     */
    public function getDetailKelulusan(int $siswaId, int $tingkatSeleksiId): array
    {
        // Implementation stub
        return [
            'lulus' => false,
            'nilai' => 0.0,
            'keterangan' => 'Belum mengikuti simulasi',
        ];
    }
}
