<?php

namespace App\Models;

use Database\Factories\AturanPemetaanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('aturan_pemetaan')]
#[Fillable(['tingkat_id', 'parameter', 'ketentuan', 'bobot_pretest', 'persen_pretest_mudah', 'persen_pretest_sedang', 'persen_pretest_sulit', 'passing_grade_pretest', 'bobot_simulasi', 'persen_simulasi_mudah', 'persen_simulasi_sedang', 'persen_simulasi_sulit', 'passing_grade_simulasi', 'latihan_min_nilai', 'pretest_jumlah_soal', 'pretest_min_soal_per_materi', 'simulasi_maks_percobaan', 'jumlah_materi_wajib'])]
#[UseFactory(AturanPemetaanFactory::class)]
class AturanPemetaan extends Model
{
    /** @use HasFactory<AturanPemetaanFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TingkatSeleksi, $this>
     */
    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class);
    }
}
