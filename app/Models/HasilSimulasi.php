<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasilSimulasi extends Model
{
    use HasFactory;

    protected $table = 'hasil_simulasi';

    protected $fillable = [
        'user_id', 'simulasi_id', 'pretest_id', 'nilai', 'jumlah_benar',
        'jumlah_salah', 'lulus', 'mulai_pada', 'batas_pada', 'disubmit_pada', 'selesai_pada',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'jumlah_benar' => 'integer',
            'jumlah_salah' => 'integer',
            'lulus' => 'boolean',
            'mulai_pada' => 'datetime',
            'batas_pada' => 'datetime',
            'disubmit_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function simulasi(): BelongsTo
    {
        return $this->belongsTo(Simulasi::class, 'simulasi_id');
    }

    public function pretest(): BelongsTo
    {
        return $this->belongsTo(Pretest::class, 'pretest_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(HasilSimulasiJawaban::class, 'hasil_simulasi_id');
    }
}
