<?php

namespace App\Contracts\Services;

use Carbon\CarbonInterface;

interface DashboardAdminServiceInterface
{
    /**
     * Ringkasan angka untuk layar dashboard Super Admin: siswa aktif,
     * pengerjaan per jenis, siswa per tingkat aktif, rata-rata nilai
     * per jenis, dan analitik pengguna.
     *
     * Filter tanggal ($dari dan $sampai, boleh null) hanya memengaruhi
     * analitik_pengguna dan inklusif per hari sesuai zona waktu aplikasi.
     * Angka lain selalu seluruh waktu.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(?CarbonInterface $dari = null, ?CarbonInterface $sampai = null): array;
}
