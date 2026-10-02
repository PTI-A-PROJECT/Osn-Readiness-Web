<?php

namespace App\Exceptions;

/**
 * PerhitunganKonfigurasiException - Calculation service misconfigured or returns invalid response.
 *
 * HTTP 502 Bad Gateway - service configuration error, not a temporary failure.
 */
class PerhitunganKonfigurasiException extends BisnisException
{
    public function __construct(
        string $message = 'Layanan perhitungan salah konfigurasi.',
        string $detail = '',
    ) {
        parent::__construct(
            message: $message,
            kode: 'PERHITUNGAN_KONFIGURASI_ERROR',
            status: 502,
            detail: $detail,
        );
    }
}
