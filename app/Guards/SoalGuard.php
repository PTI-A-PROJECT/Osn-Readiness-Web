<?php

namespace App\Guards;

use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Models\Soal;
use Illuminate\Validation\ValidationException;

/**
 * Guard to protect forbidden fields when updating a Soal that is already in use.
 *
 * If a Soal has any pengerjaan records associated with it, certain fields
 * become read-only to maintain data integrity:
 * - pertanyaan
 * - pilihan_jawaban
 * - kunci_jawaban
 * - level
 *
 * Soal dipakai berarti barisnya dirujuk oleh jawaban pre-test atau jawaban
 * quiz yang sudah terekam.
 */
class SoalGuard
{
    public static function isInUse(Soal $soal): bool
    {
        return app(SoalRepositoryInterface::class)->sedangDipakai($soal);
    }

    /**
     * Validate that forbidden fields are not being updated if soal is in use.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function validateUpdate(Soal $soal, array $data): void
    {
        if (! self::isInUse($soal)) {
            return;
        }

        $forbiddenFields = ['pertanyaan', 'pilihan_jawaban', 'kunci_jawaban', 'level'];
        $attemptedChanges = array_intersect(array_keys($data), $forbiddenFields);

        if (! empty($attemptedChanges)) {
            $fields = implode(', ', $attemptedChanges);
            throw ValidationException::withMessages([
                'soal' => "Tidak bisa mengubah {$fields} pada soal yang sedang digunakan.",
            ]);
        }
    }

    /**
     * Filter out forbidden fields if soal is in use.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filterForUpdate(Soal $soal, array $data): array
    {
        if (! self::isInUse($soal)) {
            return $data;
        }

        $forbiddenFields = ['pertanyaan', 'pilihan_jawaban', 'kunci_jawaban', 'level'];

        return array_diff_key($data, array_flip($forbiddenFields));
    }
}
