<?php

namespace App\Exceptions;

/**
 * KuotaSimulasiHabisException - Thrown when simulasi quota is exhausted.
 */
class KuotaSimulasiHabisException extends BisnisException
{
    protected string $kode = 'KUOTA_SIMULASI_HABIS';

    protected int $statusCode = 400;

    public function __construct(string $message = 'Kuota simulasi telah habis.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
