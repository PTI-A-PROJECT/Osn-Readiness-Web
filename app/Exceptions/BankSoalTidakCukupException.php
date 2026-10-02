<?php

namespace App\Exceptions;

/**
 * BankSoalTidakCukupException - Thrown when question bank is insufficient.
 */
class BankSoalTidakCukupException extends BisnisException
{
    protected string $kode = 'BANK_SOAL_TIDAK_CUKUP';

    protected int $statusCode = 500;

    public function __construct(string $message = 'Bank soal tidak cukup untuk menyusun simulasi.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
