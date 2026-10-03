<?php

namespace App\DTOs;

/**
 * TahapSiswa - Enum for student progression stages.
 */
enum TahapSiswa: string
{
    case BELUM_MULAI = 'belum_mulai';
    case PRETEST_SELESAI = 'pretest_selesai';
    case PUTARAN_AKTIF = 'putaran_aktif';
    case SIMULASI_SELESAI = 'simulasi_selesai';
    case SUDAH_LULUS = 'sudah_lulus';
}
