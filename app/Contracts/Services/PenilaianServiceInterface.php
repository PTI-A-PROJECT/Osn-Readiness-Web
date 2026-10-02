<?php

namespace App\Contracts\Services;

use App\Exceptions\PerhitunganKonfigurasiException;
use App\Exceptions\PerhitunganTidakTersediaException;

/**
 * PenilaianServiceInterface - Contract for grading and result processing.
 *
 * Handles finalization of grades for pretest, simulasi, and latihan exams.
 * Called by NilaiUlangJob with retry logic and error handling.
 */
interface PenilaianServiceInterface
{
    /**
     * Finalize grading for a specific jenis and ID.
     *
     * Implementation should:
     * 1. Load the attempt with answers
     * 2. Call PerhitunganClient to calculate scores
     * 3. Store results in database
     * 4. Update student progress
     *
     * @param  string  $jenis  - 'pretest', 'simulasi', or 'latihan'
     * @param  int  $id  - ID of the record to grade
     *
     * @throws PerhitunganTidakTersediaException - Temporary failure, can retry
     * @throws PerhitunganKonfigurasiException - Configuration error, do not retry
     */
    public function selesaikanPenilaian(string $jenis, int $id): void;

    /**
     * Queue a grading job for async processing.
     *
     * Dispatches NilaiUlangJob to process grading asynchronously.
     *
     * @param  string  $jenis  - 'pretest', 'simulasi', or 'latihan'
     * @param  int  $id  - ID of the record to grade
     */
    public function queueGrading(string $jenis, int $id): void;
}
