<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiMateri extends Model
{
    use HasFactory;

    protected $table = 'rekomendasi_materi';

    protected $fillable = ['pretest_id', 'materi_id', 'wajib', 'prioritas'];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'prioritas' => 'integer',
        ];
    }

    public function pretest(): BelongsTo
    {
        return $this->belongsTo(Pretest::class, 'pretest_id');
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }
}
