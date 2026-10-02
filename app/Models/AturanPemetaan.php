<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanPemetaan extends Model
{
    use HasFactory;

    protected $table = 'aturan_pemetaan';

    protected $fillable = [
        'tingkat_seleksi_id',
        'pretest_jumlah_soal',
        'pretest_persen_level_mudah',
        'pretest_persen_level_sedang',
        'pretest_persen_level_sulit',
        'pretest_min_soal_per_materi',
        'simulasi_jumlah_soal',
        'simulasi_persen_level_mudah',
        'simulasi_persen_level_sedang',
        'simulasi_persen_level_sulit',
        'simulasi_maks_percobaan',
        'simulasi_durasi_menit',
        'bobot',
        'passing_grade',
        'jumlah_materi_wajib',
        'latihan_min_soal',
        'latihan_min_nilai',
    ];

    protected function casts(): array
    {
        return [
            'pretest_jumlah_soal' => 'integer',
            'pretest_persen_level_mudah' => 'integer',
            'pretest_persen_level_sedang' => 'integer',
            'pretest_persen_level_sulit' => 'integer',
            'pretest_min_soal_per_materi' => 'integer',
            'simulasi_jumlah_soal' => 'integer',
            'simulasi_persen_level_mudah' => 'integer',
            'simulasi_persen_level_sedang' => 'integer',
            'simulasi_persen_level_sulit' => 'integer',
            'simulasi_maks_percobaan' => 'integer',
            'simulasi_durasi_menit' => 'integer',
            'bobot' => 'integer',
            'passing_grade' => 'integer',
            'jumlah_materi_wajib' => 'integer',
            'latihan_min_soal' => 'integer',
            'latihan_min_nilai' => 'integer',
        ];
    }

    public function tingkatSeleksi(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_seleksi_id');
    }
}
