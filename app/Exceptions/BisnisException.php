<?php

namespace App\Exceptions;

use Exception;

/**
 * BisnisException - Base exception for all business logic errors.
 *
 * Provides consistent error response format with:
 * - kode: business error code
 * - detail: additional detail information
 * - statusCode: HTTP status code
 */
class BisnisException extends Exception
{
    protected string $kode = 'BISNIS_ERROR';

    protected string $detail = '';

    protected int $statusCode = 400;

    public function __construct(
        string $message = '',
        string $kode = '',
        string $detail = '',
        int $statusCode = 400,
    ) {
        parent::__construct($message);

        if ($kode) {
            $this->kode = $kode;
        }
        $this->detail = $detail;
        $this->statusCode = $statusCode;
    }

    public function getKode(): string
    {
        return $this->kode;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
