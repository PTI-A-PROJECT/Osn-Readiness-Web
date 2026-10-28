<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Services\GoogleOAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleOAuthController
{
    public function __construct(
        private readonly GoogleOAuthServiceInterface $googleService,
    ) {}

    public function redirect(): RedirectResponse
    {
        return $this->googleService->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        return $this->googleService->callback($request);
    }
}
