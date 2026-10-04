<?php

namespace App\Contracts\Services;

/**
 * Kerangka yang diisi pemilik paketnya masing-masing.
 *
 * Bentuk methodnya sudah FINAL di app/Contracts/Services/PenilaianServiceInterface.php
 * supaya NilaiUlangJob bisa memanggilnya tanpa tahu detail tiap jenis.
 */
interface SimulasiServiceInterface extends PenilaianServiceInterface
{
    /**
     * Tutup semua percobaan kedaluwarsa atas nama sistem. Mengembalikan
     * jumlah yang berhasil ditutup.
     */
    public function tutupKedaluwarsa(): int;
}
