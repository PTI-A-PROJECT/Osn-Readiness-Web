<?php

namespace App\Clients;

use App\Contracts\Clients\PerhitunganClientInterface;
use App\DTOs\PermintaanSoal;
use App\Exceptions\PerhitunganKonfigurasiException;
use App\Exceptions\PerhitunganTidakTersediaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PerhitunganClient - Integration with external Python calculation service.
 *
 * Handles penilaian (scoring) and pretest evaluation with retry logic,
 * error mapping, and response validation.
 *
 * Endpoints:
 * - POST /hitung/penilaian - Calculate simulasi/pretest scores
 * - POST /hitung/pretest - Evaluate pretest performance
 */
class PerhitunganClient implements PerhitunganClientInterface
{
    private string $url;

    private string $token;

    private int $timeout;

    private int $maxRetries;

    private array $retryBackoff = [10, 30, 60, 120, 300]; // seconds

    public function __construct()
    {
        $config = config('services.perhitungan');

        if (! $config || ! $config['token']) {
            throw new PerhitunganKonfigurasiException(
                'Perhitungan service token not configured',
                'Set PERHITUNGAN_TOKEN in .env'
            );
        }

        $this->url = rtrim($config['url'], '/');
        $this->token = $config['token'];
        $this->timeout = $config['timeout'] ?? 5;
        $this->maxRetries = $config['retry'] ?? 2;
    }

    /**
     * Calculate nilai (score) for a simulasi/pretest attempt.
     *
     * @param  array<int, array{soalId: int, jawaban: ?string}>  $answers
     * @return array{nilai: float, benar: int, salah: int, kosong: int}
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    public function hitungNilai(int $simulasiId, array $answers): array
    {
        $payload = [
            'simulasi_id' => $simulasiId,
            'answers' => $answers,
        ];

        $response = $this->callWithRetry('/hitung/penilaian', $payload);

        return $this->validateNilaiResponse($response);
    }

    /**
     * Calculate ranking for a putaran based on nilai results.
     *
     * @param  array<int, float>  $nilaiByUser  [userId => nilai]
     * @return array<int, array{rank: int, userId: int, nilai: float}>
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    public function hitungRanking(int $putaranId, array $nilaiByUser): array
    {
        $payload = [
            'putaran_id' => $putaranId,
            'nilai_by_user' => $nilaiByUser,
        ];

        $response = $this->callWithRetry('/hitung/ranking', $payload);

        return $this->validateRankingResponse($response);
    }

    /**
     * Match soal to siswa based on kompetensi and difficulty.
     *
     * @return array<array{soalId: int, kompetensiId: int, tingkatKesulitan: string}>
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    public function matchSoalToSiswa(PermintaanSoal $permintaan): array
    {
        $payload = [
            'kompetensi_ids' => $permintaan->kompetensiIds,
            'jumlah' => $permintaan->jumlah,
            'tingkat_kesulitan' => $permintaan->tingkatKesulitan,
            'versi_kurikulum' => $permintaan->versiKurikulum,
        ];

        $response = $this->callWithRetry('/hitung/match-soal', $payload);

        return $this->validateMatchSoalResponse($response);
    }

    /**
     * Validate if hasil calculation is consistent with stored values.
     *
     * @param  array{nilai: float, benar: int, salah: int, kosong: int}  $hasilData
     */
    public function validateHasil(int $hasilId, array $hasilData): bool
    {
        $payload = [
            'hasil_id' => $hasilId,
            'nilai' => $hasilData['nilai'],
            'benar' => $hasilData['benar'],
            'salah' => $hasilData['salah'],
            'kosong' => $hasilData['kosong'],
        ];

        try {
            $response = $this->callWithRetry('/hitung/validate-hasil', $payload);

            return (bool) ($response['valid'] ?? false);
        } catch (PerhitunganTidakTersediaException|PerhitunganKonfigurasiException) {
            // If service is unavailable, log and assume valid (fail open)
            Log::warning('Perhitungan service unavailable during hasil validation', [
                'hasil_id' => $hasilId,
            ]);

            return true;
        }
    }

    /**
     * Make HTTP call with retry logic.
     *
     * Retries only on connection errors, timeouts, and 5xx errors.
     * Non-5xx errors are not retried.
     *
     * @param  string  $endpoint  - e.g., '/hitung/penilaian'
     * @param  array  $payload  - Request payload
     * @return array - Response data
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    private function callWithRetry(string $endpoint, array $payload): array
    {
        $lastException = null;
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                return $this->call($endpoint, $payload);
            } catch (PerhitunganTidakTersediaException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $this->maxRetries) {
                    $backoffSeconds = $this->retryBackoff[$attempt - 1] ?? 300;
                    Log::info('Perhitungan client retry', [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'backoff_seconds' => $backoffSeconds,
                    ]);
                    sleep($backoffSeconds);
                }
            }
        }

        throw $lastException ?? new PerhitunganTidakTersediaException(
            'Perhitungan service unavailable after retries'
        );
    }

    /**
     * Make single HTTP call to calculation service.
     *
     * @throws PerhitunganTidakTersediaException
     * @throws PerhitunganKonfigurasiException
     */
    private function call(string $endpoint, array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'X-Internal-Token' => $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post("{$this->url}{$endpoint}", $payload);

            // 4xx/2xx errors handled by Laravel HTTP client as exceptions
            if ($response->failed()) {
                if ($response->status() >= 500) {
                    throw new PerhitunganTidakTersediaException(
                        "Perhitungan service error: {$response->status()}",
                        "Response: {$response->body()}"
                    );
                }

                // 4xx errors (403, 422, etc.) = configuration error
                Log::critical('Perhitungan service client error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'endpoint' => $endpoint,
                ]);

                throw new PerhitunganKonfigurasiException(
                    "Perhitungan service validation error: {$response->status()}",
                    "Check request format and service configuration. Response: {$response->body()}"
                );
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new PerhitunganTidakTersediaException(
                'Perhitungan service connection failed',
                $e->getMessage()
            );
        } catch (RequestException $e) {
            if ($e->response && $e->response->status() >= 500) {
                throw new PerhitunganTidakTersediaException(
                    'Perhitungan service error',
                    $e->getMessage()
                );
            }

            throw new PerhitunganKonfigurasiException(
                'Perhitungan service request failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Validate response structure for hitungNilai.
     *
     * @throws PerhitunganKonfigurasiException
     */
    private function validateNilaiResponse(array $response): array
    {
        $required = ['nilai', 'benar', 'salah', 'kosong'];

        foreach ($required as $field) {
            if (! isset($response[$field])) {
                throw new PerhitunganKonfigurasiException(
                    "Missing field in perhitungan response: {$field}",
                    json_encode($response)
                );
            }
        }

        // Validate types and ranges
        if (! is_numeric($response['nilai']) || ! is_int($response['benar']) ||
            ! is_int($response['salah']) || ! is_int($response['kosong'])) {
            throw new PerhitunganKonfigurasiException(
                'Invalid field types in perhitungan response',
                json_encode($response)
            );
        }

        return [
            'nilai' => (float) $response['nilai'],
            'benar' => (int) $response['benar'],
            'salah' => (int) $response['salah'],
            'kosong' => (int) $response['kosong'],
        ];
    }

    /**
     * Validate response structure for hitungRanking.
     *
     * @throws PerhitunganKonfigurasiException
     */
    private function validateRankingResponse(array $response): array
    {
        if (! is_array($response)) {
            throw new PerhitunganKonfigurasiException(
                'Ranking response must be array',
                json_encode($response)
            );
        }

        $validated = [];

        foreach ($response as $item) {
            if (! isset($item['rank'], $item['userId'], $item['nilai'])) {
                throw new PerhitunganKonfigurasiException(
                    'Missing required fields in ranking item',
                    json_encode($item)
                );
            }

            $validated[] = [
                'rank' => (int) $item['rank'],
                'userId' => (int) $item['userId'],
                'nilai' => (float) $item['nilai'],
            ];
        }

        return $validated;
    }

    /**
     * Validate response structure for matchSoalToSiswa.
     *
     * @throws PerhitunganKonfigurasiException
     */
    private function validateMatchSoalResponse(array $response): array
    {
        if (! is_array($response)) {
            throw new PerhitunganKonfigurasiException(
                'Match soal response must be array',
                json_encode($response)
            );
        }

        $validated = [];

        foreach ($response as $item) {
            if (! isset($item['soalId'], $item['kompetensiId'], $item['tingkatKesulitan'])) {
                throw new PerhitunganKonfigurasiException(
                    'Missing required fields in matched soal item',
                    json_encode($item)
                );
            }

            $validated[] = [
                'soalId' => (int) $item['soalId'],
                'kompetensiId' => (int) $item['kompetensiId'],
                'tingkatKesulitan' => (string) $item['tingkatKesulitan'],
            ];
        }

        return $validated;
    }

    /**
     * Get retry backoff schedule (for testing).
     *
     * @return array<int>
     */
    public function getRetryBackoff(): array
    {
        return $this->retryBackoff;
    }

    /**
     * Get service URL (for testing).
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
