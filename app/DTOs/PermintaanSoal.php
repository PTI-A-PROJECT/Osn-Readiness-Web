<?php

namespace App\DTOs;

/**
 * PermintaanSoal - Request for intelligent question selection.
 */
class PermintaanSoal
{
    /**
     * @param array<int> $kompetensiIds
     * @param int $jumlah Number of questions to select
     * @param string $tingkatKesulitan Difficulty level (mudah, sedang, sulit)
     * @param string $versiKurikulum Curriculum version
     */
    public function __construct(
        public readonly array $kompetensiIds,
        public readonly int $jumlah,
        public readonly string $tingkatKesulitan = 'sedang',
        public readonly string $versiKurikulum = '2013',
    ) {}

    /**
     * Create from siswa context.
     */
    public static function fromSiswa(int $siswaId, array $kompetensiIds, int $jumlah): self
    {
        return new self(
            kompetensiIds: $kompetensiIds,
            jumlah: $jumlah,
            tingkatKesulitan: 'sedang',
            versiKurikulum: '2013',
        );
    }

    /**
     * Create from silabus specification.
     */
    public static function fromSilabus(array $silabusData): self
    {
        return new self(
            kompetensiIds: $silabusData['kompetensi_ids'] ?? [],
            jumlah: $silabusData['jumlah_soal'] ?? 40,
            tingkatKesulitan: $silabusData['tingkat_kesulitan'] ?? 'sedang',
            versiKurikulum: $silabusData['versi_kurikulum'] ?? '2013',
        );
    }
}
