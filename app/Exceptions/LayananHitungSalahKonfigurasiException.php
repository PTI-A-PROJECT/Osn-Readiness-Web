<?php

namespace App\Exceptions;

/**
 * LayananHitungSalahKonfigurasiException - Thrown when calculation service is misconfigured.
 */
class LayananHitungSalahKonfigurasiException extends BisnisException
{
    protected string $kode = 'LAYANAN_HITUNG_SALAH_KONFIGURASI';

    protected int $statusCode = 500;

    public function __construct(string $message = 'Layanan hitung tidak dikonfigurasi dengan benar.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
