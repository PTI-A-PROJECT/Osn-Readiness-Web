<?php

namespace App\Services;

use App\Contracts\Services\PencatatSoalTampilServiceInterface;
use Illuminate\Support\Facades\DB;

class PencatatSoalTampilService implements PencatatSoalTampilServiceInterface
{
    public function catat(array $soalIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $soalIds)));

        if ($ids === []) {
            return;
        }

        // Satu UPDATE atomik: setiap soal dalam satu paket hanya muncul sekali,
        // jadi cukup +1. Baris jawaban untuk paket yang sama sudah dijamin
        // unik oleh masing-masing tabel.
        DB::table('soal')->whereIn('id', $ids)->increment('jumlah_tampil');
    }
}
