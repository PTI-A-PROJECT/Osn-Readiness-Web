<?php

namespace App\DTOs;

/**
 * AturanTingkat - Rules and requirements for a selection level.
 */
class AturanTingkat
{
    /**
     * @param int $tingkatSeleksiId
     * @param int $maxSiswa Maximum students allowed
     * @param float $minNilai Minimum score to pass
     * @param array<string> $syarat Eligibility requirements
     * @param array<string> $tipeMateri Material types applicable
     */
    public function __construct(
        public readonly int $tingkatSeleksiId,
        public readonly int $maxSiswa,
        public readonly float $minNilai,
        public readonly array $syarat = [],
        public readonly array $tipeMateri = [],
    ) {}

    /**
     * Create AturanTingkat from array (factory method).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tingkatSeleksiId: $data['tingkat_seleksi_id'],
            maxSiswa: $data['max_siswa'] ?? 100,
            minNilai: $data['min_nilai'] ?? 60.0,
            syarat: $data['syarat'] ?? [],
            tipeMateri: $data['tipe_materi'] ?? [],
        );
    }
}
