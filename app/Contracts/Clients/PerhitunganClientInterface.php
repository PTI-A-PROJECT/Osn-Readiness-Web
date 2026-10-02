<?php

namespace App\Contracts\Clients;

use App\DTOs\PermintaanSoal;

interface PerhitunganClientInterface
{
    /**
     * Calculate nilai (score) for a simulasi attempt.
     *
     * @param int $simulasiId
     * @param array<int, array{soalId: int, jawaban: ?string}> $answers
     * @return array{nilai: float, benar: int, salah: int, kosong: int}
     */
    public function hitungNilai(int $simulasiId, array $answers): array;

    /**
     * Calculate ranking for a putaran based on nilai results.
     *
     * @param int $putaranId
     * @param array<int, float> $nilaiByUser [userId => nilai]
     * @return array<int, array{rank: int, userId: int, nilai: float}>
     */
    public function hitungRanking(int $putaranId, array $nilaiByUser): array;

    /**
     * Match soal to siswa based on kompetensi and difficulty.
     *
     * @return array<array{soalId: int, kompetensiId: int, tingkatKesulitan: string}>
     */
    public function matchSoalToSiswa(PermintaanSoal $permintaan): array;

    /**
     * Validate if hasil calculation is consistent.
     *
     * @param array{nilai: float, benar: int, salah: int, kosong: int} $hasilData
     */
    public function validateHasil(int $hasilId, array $hasilData): bool;
}
