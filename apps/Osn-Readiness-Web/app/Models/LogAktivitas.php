<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('log_aktivitas')]
#[WithoutTimestamps]
#[Fillable([
    'user_id',
    'aksi',
    'kategori',
    'subjek_tipe',
    'subjek_id',
    'meta',
    'batch_id',
    'ip',
    'user_agent',
    'terjadi_pada',
])]
class LogAktivitas extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'terjadi_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
