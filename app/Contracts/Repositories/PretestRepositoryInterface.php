<?php

namespace App\Contracts\Repositories;

use App\Models\Pretest;

interface PretestRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Pre-test yang belum selesai di tingkat ini, kalau ada.
     */
    public function berjalan(int $userId, int $tingkatId): ?Pretest;

    /**
     * Pre-test terbaru yang sudah selesai di tingkat ini: putaran aktif.
     */
    public function putaranAktifTerbaru(int $userId, int $tingkatId): ?Pretest;
}
