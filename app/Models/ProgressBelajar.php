<?php

namespace App\Models;

use App\Enums\StatusProgress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressBelajar extends Model
{
    use HasFactory;

    protected $table = 'progress_belajar';

    protected $fillable = ['user_id', 'materi_id', 'status', 'tanggal_selesai', 'persentase'];

    protected function casts(): array
    {
        return [
            'status' => StatusProgress::class,
            'tanggal_selesai' => 'datetime',
            'persentase' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }
}
