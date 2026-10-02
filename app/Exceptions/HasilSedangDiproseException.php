<?php

namespace App\Exceptions;

/**
 * HasilSedangDiproseException - Thrown when result is still being processed.
 */
class HasilSedangDiproseException extends BisnisException
{
    protected string $kode = 'HASIL_SEDANG_DIPROSES';

    protected int $statusCode = 202;

    public function __construct(string $message = 'Hasil sedang diproses, silakan coba lagi nanti.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
