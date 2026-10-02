<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatHasil extends Model
{
    protected $table = 'riwayat_hasil';

    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'jenis', 'tingkat_id', 'tingkat_nama',
        'nilai', 'tanggal_selesai', 'created_at', 'simulasi_id',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'tanggal_selesai' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tingkatSeleksi()
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_id');
    }
}
