<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kompetensi extends Model
{
    use HasFactory;

    protected $table = 'kompetensi';

    protected $fillable = ['tingkat_seleksi_id', 'nama'];

    public function tingkatSeleksi(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_seleksi_id');
    }

    public function materi(): HasMany
    {
        return $this->hasMany(Materi::class, 'kompetensi_id');
    }
}
