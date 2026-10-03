<?php

namespace App\Contracts\Services;

use App\DTOs\StatusPutaran;

interface PutaranServiceInterface
{
    /**
     * Get status of an exam round.
     */
    public function getStatus(int $putaranId): StatusPutaran;

    /**
     * Enroll a student in a putaran.
     */
    public function enroll(int $siswaId, int $putaranId): bool;

    /**
     * Check if student can enroll in putaran.
     */
    public function canEnroll(int $siswaId, int $putaranId): bool;
}
