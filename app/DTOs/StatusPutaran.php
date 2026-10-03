<?php

namespace App\DTOs;

/**
 * StatusPutaran - Enum for exam round status.
 */
enum StatusPutaran: string
{
    case BELUM_DIMULAI = 'belum_dimulai';
    case BERJALAN = 'berjalan';
    case SELESAI = 'selesai';
}
