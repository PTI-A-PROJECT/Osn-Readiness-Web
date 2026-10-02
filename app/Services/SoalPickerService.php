<?php

namespace App\Services;

use App\Contracts\Services\SoalPickerServiceInterface;
use App\DTOs\PermintaanSoal;
use App\DTOs\SoalTerpilih;

class SoalPickerService implements SoalPickerServiceInterface
{
    /**
     * @return array<SoalTerpilih>
     */
    public function pilihSoal(PermintaanSoal $permintaan): array
    {
        // Implementation stub
        return [];
    }

    /**
     * @param array<SoalTerpilih> $soalTerpilih
     */
    public function validasiSoal(PermintaanSoal $permintaan, array $soalTerpilih): bool
    {
        // Implementation stub
        return true;
    }
}
