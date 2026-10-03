<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizPengerjaan extends Model
{
    use HasFactory;

    protected $table = 'quiz_pengerjaan';

    protected $fillable = ['user_id', 'quiz_id', 'nilai', 'disubmit_pada', 'selesai_pada'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'disubmit_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(QuizJawaban::class, 'quiz_pengerjaan_id');
    }
}
