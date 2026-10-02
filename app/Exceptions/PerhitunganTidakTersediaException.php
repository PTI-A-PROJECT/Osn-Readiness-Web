<?php

namespace App\Exceptions;

/**
 * PerhitunganTidakTersediaException - Calculation service is temporarily unavailable.
 *
 * HTTP 503 Service Unavailable - temporary failure, can be retried.
 */
class PerhitunganTidakTersediaException extends BisnisException
{
    public function __construct(
        string $message = 'Layanan perhitungan sedang tidak tersedia.',
        string $detail = '',
    ) {
        parent::__construct(
            message: $message,
            kode: 'PERHITUNGAN_TIDAK_TERSEDIA',
            status: 503,
            detail: $detail,
        );
    }
}
