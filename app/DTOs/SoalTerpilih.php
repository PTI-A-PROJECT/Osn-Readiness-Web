<?php

namespace App\DTOs;

/**
 * SoalTerpilih - Selected question with metadata.
 */
class SoalTerpilih
{
    /**
     * @param int $soalId
     * @param int $kompetensiId
     * @param string $tingkatKesulitan
     * @param int $waktuEstimasi Estimated time in minutes
     */
    public function __construct(
        public readonly int $soalId,
        public readonly int $kompetensiId,
        public readonly string $tingkatKesulitan,
        public readonly int $waktuEstimasi = 3,
    ) {}

    /**
     * Create from soal model or array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            soalId: $data['soal_id'] ?? $data['id'],
            kompetensiId: $data['kompetensi_id'],
            tingkatKesulitan: $data['tingkat_kesulitan'] ?? 'sedang',
            waktuEstimasi: $data['waktu_estimasi'] ?? 3,
        );
    }

    /**
     * Get indexed collection by soal ID.
     *
     * @param array<self> $soalTerpilihList
     * @return array<int, self>
     */
    public static function indexBysoalId(array $soalTerpilihList): array
    {
        $indexed = [];
        foreach ($soalTerpilihList as $soal) {
            $indexed[$soal->soalId] = $soal;
        }

        return $indexed;
    }
}
