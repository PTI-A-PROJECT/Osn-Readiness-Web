<?php

namespace App\Jobs;

use App\Contracts\Services\PenilaianServiceInterface;
use App\Exceptions\PerhitunganKonfigurasiException;
use App\Exceptions\PerhitunganTidakTersediaException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * NilaiUlangJob - Async job for recalculating and updating grades.
 *
 * Handles grading for pretest, simulasi, and latihan with:
 * - Retry logic: 5 attempts with backoff [10, 30, 60, 120, 300]
 * - Idempotency: safe to run multiple times
 * - Error handling: temporary failures vs configuration errors
 *
 * Usage:
 *   NilaiUlangJob::dispatch('pretest', $pretestId);
 *   NilaiUlangJob::dispatch('simulasi', $simulasiId);
 *   NilaiUlangJob::dispatch('latihan', $latihanId);
 */
class NilaiUlangJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Backoff strategy for retries (in seconds).
     * Used by Laravel queue: after each retry, waits backoff[$attempt] seconds.
     */
    public array $backoff = [10, 30, 60, 120, 300];

    /**
     * Maximum retry attempts (5 = 5 retries after initial attempt).
     */
    public int $tries = 5;

    /**
     * Maximum execution time (seconds). Job will timeout if exceeds.
     */
    public int $timeout = 60;

    public function __construct(
        private readonly string $jenis, // 'pretest', 'simulasi', 'latihan'
        private readonly int $id,        // ID of the record to grade
    ) {}

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle(PenilaianServiceInterface $penilaianService): void
    {
        Log::info('NilaiUlangJob started', [
            'jenis' => $this->jenis,
            'id' => $this->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Validate jenis is supported
            if (! $this->isJenisSupported()) {
                Log::error('NilaiUlangJob unsupported jenis', [
                    'jenis' => $this->jenis,
                    'id' => $this->id,
                ]);
                $this->fail(new \InvalidArgumentException("Unsupported jenis: {$this->jenis}"));

                return;
            }

            // Call appropriate service method based on jenis
            // The service resolves grading via PerhitunganClient internally
            $penilaianService->selesaikanPenilaian($this->jenis, $this->id);

            Log::info('NilaiUlangJob completed', [
                'jenis' => $this->jenis,
                'id' => $this->id,
            ]);
        } catch (PerhitunganTidakTersediaException $e) {
            // Temporary failure - should be retried
            Log::warning('NilaiUlangJob temporary failure - will retry', [
                'jenis' => $this->jenis,
                'id' => $this->id,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            // Release job back to queue for retry
            // Laravel will use backoff[$attempt] to delay
            $this->release();
        } catch (PerhitunganKonfigurasiException $e) {
            // Configuration error - fail permanently
            Log::error('NilaiUlangJob configuration error', [
                'jenis' => $this->jenis,
                'id' => $this->id,
                'message' => $e->getMessage(),
                'detail' => $e->getDetail(),
            ]);

            // Fail the job - don't retry
            $this->fail($e);
        } catch (\Throwable $e) {
            // Other exceptions - log and fail
            Log::error('NilaiUlangJob unexpected error', [
                'jenis' => $this->jenis,
                'id' => $this->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Called when job is retried after failed attempt.
     * Useful for cleanup or state management between retries.
     */
    public function retrying(): void
    {
        Log::info('NilaiUlangJob retrying', [
            'jenis' => $this->jenis,
            'id' => $this->id,
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Called when job exceeds max retries or fails permanently.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('NilaiUlangJob failed permanently', [
            'jenis' => $this->jenis,
            'id' => $this->id,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        // TODO: Optionally send notification to admin/supervisor
        // NotifyGradingFailure::dispatch($this->jenis, $this->id);
    }

    /**
     * Check if jenis is supported.
     */
    private function isJenisSupported(): bool
    {
        return in_array($this->jenis, ['pretest', 'simulasi', 'latihan']);
    }

    /**
     * Get the number of seconds before a released job will be available.
     * Called by Laravel queue to determine backoff timing.
     */
    public function backoffUsingSeconds(): array
    {
        return $this->backoff;
    }

    /**
     * Uniquely identify this job to prevent duplicate processing.
     *
     * Returns null by default (no uniqueness constraint).
     * If implemented, job will be deduplicated within the timeout window.
     */
    public function uniqueId(): string
    {
        return "{$this->jenis}:{$this->id}";
    }

    /**
     * Get the unique job id timeout (seconds).
     * Only used if uniqueId() is implemented.
     *
     * Set to 1 hour to prevent duplicate jobs within that window.
     */
    public function uniqueFor(): int
    {
        return 3600;
    }
}
