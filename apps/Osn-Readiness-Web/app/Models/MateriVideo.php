<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MateriVideo extends Model
{
    protected $fillable = ['materi_id', 'judul', 'youtube_url', 'youtube_id', 'tipe', 'urutan'];

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class);
    }
}
