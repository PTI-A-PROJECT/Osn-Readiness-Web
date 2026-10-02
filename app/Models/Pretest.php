<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pretest extends Model
{
    use HasFactory;

    protected $table = 'pretest';

    protected $fillable = ['user_id', 'tingkat_seleksi_id', 'nilai', 'disubmit_pada', 'selesai_pada'];

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

    public function tingkatSeleksi(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_seleksi_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(PretestJawaban::class, 'pretest_id');
    }

    public function pemetaanMateri(): HasMany
    {
        return $this->hasMany(PemetaanMateri::class, 'pretest_id');
    }

    public function rekomendasiMateri(): HasMany
    {
        return $this->hasMany(RekomendasiMateri::class, 'pretest_id');
    }

    public function hasilSimulasi(): HasMany
    {
        return $this->hasMany(HasilSimulasi::class, 'pretest_id');
    }
}
