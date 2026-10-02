<?php

namespace App\Exceptions;

/**
 * TingkatTerkunciException - Thrown when a selection level is locked.
 */
class TingkatTerkunciException extends BisnisException
{
    protected string $kode = 'TINGKAT_TERKUNCI';

    protected int $statusCode = 403;

    public function __construct(string $message = 'Tingkat seleksi telah ditutup.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
