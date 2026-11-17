<?php

namespace App\Contracts\Services;

/**
 * Menaikkan hitungan kemunculan soal (soal.jumlah_tampil).
 *
 * Dicatat sekali per paket pengerjaan saat paket dibuat, di dalam transaksi
 * yang sama dengan pembuatan baris jawaban. Dipakai SoalPickerService untuk
 * memprioritaskan soal yang paling jarang keluar.
 */
interface PencatatSoalTampilServiceInterface
{
    /**
     * @param  array<int, int>  $soalIds
     */
    public function catat(array $soalIds): void;
}
