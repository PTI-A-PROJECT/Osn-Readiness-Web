<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TingkatSeleksi extends Model
{
    use HasFactory;

    protected $table = 'tingkat_seleksi';

    protected $fillable = ['nama', 'deskripsi', 'urutan'];

    public function kompetensi(): HasMany
    {
        return $this->hasMany(Kompetensi::class, 'tingkat_seleksi_id');
    }

    public function pretest(): HasMany
    {
        return $this->hasMany(Pretest::class, 'tingkat_seleksi_id');
    }

    public function simulasi(): HasMany
    {
        return $this->hasMany(Simulasi::class, 'tingkat_seleksi_id');
    }

    public function aturanPemetaan()
    {
        return $this->hasOne(AturanPemetaan::class, 'tingkat_seleksi_id');
    }

    public function konteksSoal(): HasMany
    {
        return $this->hasMany(KonteksSoal::class, 'tingkat_seleksi_id');
    }

    public function kenaikanTingkat(): HasMany
    {
        return $this->hasMany(KenaikanTingkat::class, 'tingkat_asal_id');
    }

    public function kenaikanTujuan(): HasMany
    {
        return $this->hasMany(KenaikanTingkat::class, 'tingkat_tujuan_id');
    }
}
