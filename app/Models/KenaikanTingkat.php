<?php

namespace App\Models;

use App\Enums\StatusKenaikan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KenaikanTingkat extends Model
{
    use HasFactory;

    protected $table = 'kenaikan_tingkat';

    protected $fillable = [
        'user_id', 'tingkat_asal_id', 'tingkat_tujuan_id', 'status',
        'nilai_terbaik', 'passing_grade', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKenaikan::class,
            'nilai_terbaik' => 'decimal:2',
            'passing_grade' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tingkatAsal(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_asal_id');
    }

    public function tingkatTujuan(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_tujuan_id');
    }
}
