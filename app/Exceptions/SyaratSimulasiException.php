<?php

namespace App\Exceptions;

/**
 * SyaratSimulasiException - Thrown when simulasi prerequisites are not met.
 */
class SyaratSimulasiException extends BisnisException
{
    protected string $kode = 'SYARAT_SIMULASI_TIDAK_TERPENUHI';

    protected int $statusCode = 400;

    public function __construct(string $message = 'Syarat untuk mengikuti simulasi belum terpenuhi.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
