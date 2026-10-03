<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemetaanMateri extends Model
{
    use HasFactory;

    protected $table = 'pemetaan_materi';

    protected $fillable = ['pretest_id', 'materi_id', 'jumlah_benar', 'jumlah_soal', 'persentase'];

    protected function casts(): array
    {
        return [
            'jumlah_benar' => 'integer',
            'jumlah_soal' => 'integer',
            'persentase' => 'decimal:2',
        ];
    }

    public function pretest(): BelongsTo
    {
        return $this->belongsTo(Pretest::class, 'pretest_id');
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }
}
