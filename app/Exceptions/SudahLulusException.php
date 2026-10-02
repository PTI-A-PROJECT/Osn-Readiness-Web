<?php

namespace App\Exceptions;

/**
 * SudahLulusException - Thrown when student has already passed.
 */
class SudahLulusException extends BisnisException
{
    protected string $kode = 'SUDAH_LULUS';

    protected int $statusCode = 409;

    public function __construct(string $message = 'Siswa sudah lulus dan tidak dapat mengikuti putaran ini.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
