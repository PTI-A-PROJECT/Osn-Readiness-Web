<?php

namespace App\Enums;

enum StatusProgress: string
{
    case BELUM_BELAJAR = 'belum_belajar';
    case SEDANG_BELAJAR = 'sedang_belajar';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match($this) {
            self::BELUM_BELAJAR => 'Belum Belajar',
            self::SEDANG_BELAJAR => 'Sedang Belajar',
            self::SELESAI => 'Selesai',
        };
    }
}
