<?php

namespace App\Exceptions;

/**
 * PerhitunganTidakTersediaException - Thrown when calculation service is unavailable.
 */
class PerhitunganTidakTersediaException extends BisnisException
{
    protected string $kode = 'PERHITUNGAN_TIDAK_TERSEDIA';

    protected int $statusCode = 503;

    public function __construct(string $message = 'Layanan perhitungan sedang tidak tersedia.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
