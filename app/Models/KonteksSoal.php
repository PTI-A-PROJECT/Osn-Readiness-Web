<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KonteksSoal extends Model
{
    use HasFactory;

    protected $table = 'konteks_soal';

    protected $fillable = ['tingkat_seleksi_id', 'deskripsi'];

    public function tingkatSeleksi(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_seleksi_id');
    }

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class, 'konteks_soal_id');
    }
}
