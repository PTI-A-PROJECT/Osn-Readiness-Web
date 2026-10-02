<?php

namespace App\Exceptions;

/**
 * WaktuHabisException - Thrown when time limit is exceeded.
 */
class WaktuHabisException extends BisnisException
{
    protected string $kode = 'WAKTU_HABIS';

    protected int $statusCode = 408;

    public function __construct(string $message = 'Waktu untuk mengerjakan simulasi telah habis.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
