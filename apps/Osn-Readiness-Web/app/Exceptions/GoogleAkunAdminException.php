<?php

namespace App\Exceptions;

class GoogleAkunAdminException extends BisnisException
{
    public function __construct()
    {
        parent::__construct(
            'Akun admin tidak dapat masuk melalui Google.',
            'GOOGLE_AKUN_ADMIN',
            403,
        );
    }
}
