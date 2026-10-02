<?php

namespace App\Exceptions;

/**
 * SimulasiBeluumDinilaiException - Thrown when simulasi result is not yet graded.
 */
class SimulasiBeluumDinilaiException extends BisnisException
{
    protected string $kode = 'SIMULASI_BELUM_DINILAI';

    protected int $statusCode = 400;

    public function __construct(string $message = 'Simulasi belum dinilai.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
