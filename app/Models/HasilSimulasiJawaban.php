<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilSimulasiJawaban extends Model
{
    use HasFactory;

    protected $table = 'hasil_simulasi_jawaban';

    protected $fillable = ['hasil_simulasi_id', 'soal_id', 'jawaban_user', 'status_benar', 'urutan', 'bobot'];

    protected function casts(): array
    {
        return [
            'status_benar' => 'boolean',
            'urutan' => 'integer',
            'bobot' => 'decimal:2',
        ];
    }

    public function hasilSimulasi(): BelongsTo
    {
        return $this->belongsTo(HasilSimulasi::class, 'hasil_simulasi_id');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}
