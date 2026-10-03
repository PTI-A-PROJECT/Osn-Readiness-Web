<?php

namespace App\Clients;

use App\Contracts\Clients\PerhitunganClientInterface;
use App\DTOs\PermintaanSoal;

/**
 * FakePerhitunganClient - Mock implementation of the Python calculation service.
 */
class FakePerhitunganClient implements PerhitunganClientInterface
{
    public function hitungNilai(int $simulasiId, array $answers): array
    {
        $benar = 0;
        $salah = 0;
        $kosong = 0;

        foreach ($answers as $answer) {
            if ($answer['jawaban'] === null) {
                $kosong++;
            } else {
                if ($answer['soalId'] % 2 === 0) {
                    $benar++;
                } else {
                    $salah++;
                }
            }
        }

        $nilai = ($benar * 4) + ($salah * -1);

        return [
            'nilai' => max(0, (float) $nilai),
            'benar' => $benar,
            'salah' => $salah,
            'kosong' => $kosong,
        ];
    }

    public function hitungRanking(int $putaranId, array $nilaiByUser): array
    {
        arsort($nilaiByUser);
        $ranking = [];
        $rank = 1;
        $lastNilai = null;
        $count = 0;

        foreach ($nilaiByUser as $userId => $nilai) {
            if ($lastNilai !== null && $nilai < $lastNilai) {
                $rank = $count + 1;
            }
            $ranking[$userId] = [
                'rank' => $rank,
                'userId' => $userId,
                'nilai' => $nilai,
            ];
            $lastNilai = $nilai;
            $count++;
        }

        return array_values($ranking);
    }

    public function matchSoalToSiswa(PermintaanSoal $permintaan): array
    {
        $mockSoalIds = [1, 3, 5, 7, 9, 11, 13, 15, 17, 19];

        return array_map(function ($soalId) {
            return [
                'soalId' => $soalId,
                'kompetensiId' => ($soalId % 3) + 1,
                'tingkatKesulitan' => ['mudah', 'sedang', 'sulit'][($soalId - 1) % 3],
            ];
        }, $mockSoalIds);
    }

    public function validateHasil(int $hasilId, array $hasilData): bool
    {
        $expectedNilai = ($hasilData['benar'] * 4) + ($hasilData['salah'] * -1);

        return $hasilData['nilai'] === max(0, (float) $expectedNilai);
    }
}
