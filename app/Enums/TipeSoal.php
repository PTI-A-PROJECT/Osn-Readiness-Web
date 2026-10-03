<?php

namespace App\Enums;

enum TipeSoal: string
{
    case PILIHAN_GANDA = 'pilihan_ganda';
    case ISIAN = 'isian';

    public function label(): string
    {
        return match($this) {
            self::PILIHAN_GANDA => 'Pilihan Ganda',
            self::ISIAN => 'Isian',
        };
    }
}
