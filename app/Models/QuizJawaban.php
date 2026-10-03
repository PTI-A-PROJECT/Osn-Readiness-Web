<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizJawaban extends Model
{
    use HasFactory;

    protected $table = 'quiz_jawaban';

    protected $fillable = ['quiz_pengerjaan_id', 'soal_id', 'jawaban_user', 'status_benar', 'urutan', 'bobot'];

    protected function casts(): array
    {
        return [
            'status_benar' => 'boolean',
            'urutan' => 'integer',
            'bobot' => 'decimal:2',
        ];
    }

    public function quizPengerjaan(): BelongsTo
    {
        return $this->belongsTo(QuizPengerjaan::class, 'quiz_pengerjaan_id');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}
