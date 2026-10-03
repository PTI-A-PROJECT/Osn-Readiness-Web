<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PretestJawaban extends Model
{
    use HasFactory;

    protected $table = 'pretest_jawaban';

    protected $fillable = ['pretest_id', 'soal_id', 'jawaban_user', 'status_benar', 'urutan', 'bobot'];

    protected function casts(): array
    {
        return [
            'status_benar' => 'boolean',
            'urutan' => 'integer',
            'bobot' => 'decimal:2',
        ];
    }

    public function pretest(): BelongsTo
    {
        return $this->belongsTo(Pretest::class, 'pretest_id');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}
