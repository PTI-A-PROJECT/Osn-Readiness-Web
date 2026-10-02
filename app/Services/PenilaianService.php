<?php

namespace App\Services;

use App\Clients\PerhitunganClient;
use App\Contracts\Services\PenilaianServiceInterface;
use App\Jobs\NilaiUlangJob;
use Illuminate\Support\Facades\Log;

/**
 * PenilaianService - Handles grading and result processing for exams.
 *
 * Processes grades for pretest, simulasi, and latihan by:
 * 1. Calling PerhitunganClient to calculate scores
 * 2. Storing results in database
 * 3. Updating student progress
 *
 * Called by NilaiUlangJob for async grading.
 */
class PenilaianService implements PenilaianServiceInterface
{
    public function __construct(
        private readonly PerhitunganClient $perhitunganClient,
    ) {}

    /**
     * Finalize grading for a specific jenis and ID.
     *
     * This is the main entry point called by NilaiUlangJob.
     * The actual implementation depends on jenis (pretest, simulasi, latihan).
     *
     * @param  string  $jenis  - 'pretest', 'simulasi', or 'latihan'
     * @param  int  $id  - ID of the record to grade (PretestJawaban, SimulasiJawaban, or LatihanJawaban ID)
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    public function selesaikanPenilaian(string $jenis, int $id): void
    {
        Log::info('PenilaianService.selesaikanPenilaian', [
            'jenis' => $jenis,
            'id' => $id,
        ]);

        match ($jenis) {
            'pretest' => $this->selesaikanPenilaianPretest($id),
            'simulasi' => $this->selesaikanPenilaianSimulasi($id),
            'latihan' => $this->selesaikanPenilaianLatihan($id),
            default => throw new \InvalidArgumentException("Unsupported jenis: {$jenis}"),
        };
    }

    /**
     * Finalize grading for pretest.
     *
     * TODO: Implementation depends on pretest schema and PerhitunganClient response.
     * When implemented by team:
     * 1. Load pretest attempt with answers
     * 2. Call $this->perhitunganClient->hitungNilai(...)
     * 3. Store hasil_pretest with nilai, benar, salah, kosong
     * 4. Update siswa progress
     */
    private function selesaikanPenilaianPretest(int $pretestId): void
    {
        Log::debug('Finalize pretest grading', ['pretest_id' => $pretestId]);

        // Stub: to be implemented by Orang 1 (B1-B or higher)
        // This is where Orang 1 fills in the actual grading logic
    }

    /**
     * Finalize grading for simulasi.
     *
     * TODO: Implementation depends on simulasi schema and PerhitunganClient response.
     * When implemented by team:
     * 1. Load simulasi attempt with answers
     * 2. Call $this->perhitunganClient->hitungNilai(...)
     * 3. Store hasil_simulasi with nilai, benar, salah, kosong
     * 4. Update siswa progress and ranking
     */
    private function selesaikanPenilaianSimulasi(int $simulasiId): void
    {
        Log::debug('Finalize simulasi grading', ['simulasi_id' => $simulasiId]);

        // Stub: to be implemented by Orang 1 (B3-A)
        // This is where Orang 1 fills in the actual grading logic
    }

    /**
     * Finalize grading for latihan (exercise/quiz).
     *
     * TODO: Implementation depends on latihan schema and PerhitunganClient response.
     * When implemented by team:
     * 1. Load latihan attempt with answers
     * 2. Call $this->perhitunganClient->hitungNilai(...)
     * 3. Store quiz result with nilai
     * 4. Update siswa progress (track best score per latihan)
     */
    private function selesaikanPenilaianLatihan(int $latihanId): void
    {
        Log::debug('Finalize latihan grading', ['latihan_id' => $latihanId]);

        // Stub: to be implemented by Orang 2 (B2-B)
        // This is where Orang 2 fills in the actual grading logic for exercises
    }

    /**
     * Called when a pretest/simulasi/latihan is submitted.
     * Triggers async grading via NilaiUlangJob.
     *
     * TODO: This method would be called from controllers or event listeners
     * when a student submits their answers.
     */
    public function queueGrading(string $jenis, int $id): void
    {
        // Dispatch NilaiUlangJob to process grading asynchronously
        NilaiUlangJob::dispatch($jenis, $id);

        Log::info('Grading queued', [
            'jenis' => $jenis,
            'id' => $id,
        ]);
    }
}
