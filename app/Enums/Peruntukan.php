<?php

namespace App\Enums;

enum Peruntukan: string
{
    case PRETEST = 'pretest';
    case LATIHAN = 'latihan';
    case SIMULASI = 'simulasi';

    public function label(): string
    {
        return match($this) {
            self::PRETEST => 'Pre-test',
            self::LATIHAN => 'Latihan',
            self::SIMULASI => 'Simulasi',
        };
    }
}
