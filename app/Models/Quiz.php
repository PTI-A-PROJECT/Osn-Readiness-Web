<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $table = 'quiz';

    protected $fillable = ['materi_id', 'jumlah_soal'];

    protected function casts(): array
    {
        return [
            'jumlah_soal' => 'integer',
        ];
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function pengerjaan(): HasMany
    {
        return $this->hasMany(QuizPengerjaan::class, 'quiz_id');
    }
}
