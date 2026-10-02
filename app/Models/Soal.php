<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Enums\TipeSoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Soal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'soal';

    protected $fillable = [
        'materi_id', 'konteks_soal_id', 'pertanyaan', 'pilihan',
        'jawaban_benar', 'level', 'peruntukan', 'tipe_soal', 'gambar', 'id_sumber',
    ];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'peruntukan' => Peruntukan::class,
            'tipe_soal' => TipeSoal::class,
            'pilihan' => 'json',
        ];
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function konteksSoal(): BelongsTo
    {
        return $this->belongsTo(KonteksSoal::class, 'konteks_soal_id');
    }

    public function pembahasan(): HasOne
    {
        return $this->hasOne(Pembahasan::class, 'soal_id');
    }

    public function pretestJawaban(): HasMany
    {
        return $this->hasMany(PretestJawaban::class, 'soal_id');
    }

    public function quizJawaban(): HasMany
    {
        return $this->hasMany(QuizJawaban::class, 'soal_id');
    }

    public function hasilSimulasiJawaban(): HasMany
    {
        return $this->hasMany(HasilSimulasiJawaban::class, 'soal_id');
    }
}
