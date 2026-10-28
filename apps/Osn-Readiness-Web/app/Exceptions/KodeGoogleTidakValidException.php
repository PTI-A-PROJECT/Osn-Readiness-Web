<?php

namespace App\Exceptions;

class KodeGoogleTidakValidException extends BisnisException
{
    public function __construct()
    {
        parent::__construct(
            'Kode login Google tidak sah atau sudah kedaluwarsa.',
            'KODE_GOOGLE_TIDAK_VALID',
            401,
        );
    }
}
