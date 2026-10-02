<?php

namespace App\Exceptions;

/**
 * BelumPretestException - Thrown when pretest has not been completed.
 */
class BelumPretestException extends BisnisException
{
    protected string $kode = 'BELUM_PRETEST';

    protected int $statusCode = 400;

    public function __construct(string $message = 'Pretest belum diselesaikan.', string $detail = '')
    {
        parent::__construct($message, $this->kode, $detail, $this->statusCode);
    }
}
