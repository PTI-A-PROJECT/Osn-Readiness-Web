<?php

namespace App\Contracts\Services;

interface PenilaianServiceInterface
{
    /**
     * Record and calculate grades for a simulasi.
     */
    public function nilaiBerjalan(int $simulasiId, int $siswaId): void;

    /**
     * Finalize grades and store hasil.
     */
    public function finalizeNilai(int $simulasiId): array;

    /**
     * Get nilai details for a simulasi.
     */
    public function getNilai(int $simulasiId): array;
}
