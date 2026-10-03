<?php

namespace App\Contracts\Services;

use App\DTOs\AturanTingkat;

interface AturanServiceInterface
{
    /**
     * Get rules for a selection level.
     */
    public function getAturan(int $tingkatSeleksiId): AturanTingkat;

    /**
     * Check if student meets eligibility requirements.
     */
    public function cekEligibilitas(int $siswaId, int $tingkatSeleksiId): bool;
}
