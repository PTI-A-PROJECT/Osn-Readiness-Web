<?php

namespace App\Exceptions;

/**
 * PutaranMasihBerjalanException - Thrown when trying to proceed with an active exam round.
 */
class PutaranMasihBerjalanException extends BisnisException
{
    protected string $kode = 'PUTARAN_MASIH_BERJALAN';

    protected int $statusCode = 409;

    public function __construct(string $message = 'Putaran masih berlangsung, tidak dapat melanjutkan.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
