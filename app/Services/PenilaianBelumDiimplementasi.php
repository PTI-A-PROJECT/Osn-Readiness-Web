<?php

namespace App\Services;

use App\Contracts\Services\LatihanServiceInterface;
use App\Contracts\Services\SimulasiServiceInterface;
use LogicException;

/**
 * Penemporan untuk interface yang kontraknya sudah final tetapi implementasinya
 * belum dikerjakan.
 *
 * PretestService sudah ada di B2-A. Latihan diisi B2-B dan simulasi diisi B3-A.
 * Stub ini hanya menjaga NilaiUlangJob tetap bisa di-resolve untuk dua jenis itu,
 * dan tidak pernah diam-diam menandai nilai sebagai selesai.
 */
class PenilaianBelumDiimplementasi implements LatihanServiceInterface, SimulasiServiceInterface
{
    public function selesaikanPenilaian(int $id): void
    {
        throw new LogicException(
            'Penilaian untuk jenis ini belum diimplementasikan. Lihat B2-B untuk latihan dan B3-A untuk simulasi.'
        );
    }
}
