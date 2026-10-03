<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Simulasi extends Model
{
    use HasFactory;

    protected $table = 'simulasi';

    protected $fillable = ['tingkat_seleksi_id', 'nama', 'jumlah_soal', 'durasi_menit', 'is_aktif'];

    protected function casts(): array
    {
        return [
            'jumlah_soal' => 'integer',
            'durasi_menit' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    public function tingkatSeleksi(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_seleksi_id');
    }

    public function hasilSimulasi(): HasMany
    {
        return $this->hasMany(HasilSimulasi::class, 'simulasi_id');
    }
}
