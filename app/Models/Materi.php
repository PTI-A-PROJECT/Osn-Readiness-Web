<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Materi extends Model
{
    use HasFactory;

    protected $table = 'materi';

    protected $fillable = ['kompetensi_id', 'judul', 'isi_materi', 'urutan', 'id_sumber'];

    public function kompetensi(): BelongsTo
    {
        return $this->belongsTo(Kompetensi::class, 'kompetensi_id');
    }

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class, 'materi_id');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'materi_id');
    }

    public function progressBelajar(): HasMany
    {
        return $this->hasMany(ProgressBelajar::class, 'materi_id');
    }

    public function pemetaanMateri(): HasMany
    {
        return $this->hasMany(PemetaanMateri::class, 'materi_id');
    }

    public function rekomendasiMateri(): HasMany
    {
        return $this->hasMany(RekomendasiMateri::class, 'materi_id');
    }
}
