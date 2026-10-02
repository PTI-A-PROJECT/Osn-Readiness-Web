<?php

namespace App\Enums;

enum StatusKenaikan: string
{
    case LULUS = 'lulus';
    case TIDAK_LULUS = 'tidak_lulus';

    public function label(): string
    {
        return match($this) {
            self::LULUS => 'Lulus',
            self::TIDAK_LULUS => 'Tidak Lulus',
        };
    }
}
