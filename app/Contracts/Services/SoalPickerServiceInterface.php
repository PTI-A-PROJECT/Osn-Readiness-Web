<?php

namespace App\Contracts\Services;

use App\DTOs\PermintaanSoal;
use App\DTOs\SoalTerpilih;

interface SoalPickerServiceInterface
{
    /**
     * Select questions intelligently based on request criteria.
     *
     * @return array<SoalTerpilih>
     */
    public function pilihSoal(PermintaanSoal $permintaan): array;

    /**
     * Validate if selected questions meet criteria.
     *
     * @param array<SoalTerpilih> $soalTerpilih
     */
    public function validasiSoal(PermintaanSoal $permintaan, array $soalTerpilih): bool;
}
