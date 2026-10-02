<?php

namespace App\Enums;

enum Level: string
{
    case MUDAH = 'mudah';
    case SEDANG = 'sedang';
    case SULIT = 'sulit';

    public function label(): string
    {
        return match($this) {
            self::MUDAH => 'Mudah',
            self::SEDANG => 'Sedang',
            self::SULIT => 'Sulit',
        };
    }
}
