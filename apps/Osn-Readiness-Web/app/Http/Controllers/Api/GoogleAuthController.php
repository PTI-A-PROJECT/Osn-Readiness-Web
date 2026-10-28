<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\GoogleOAuthServiceInterface;
use App\Http\Requests\Auth\GoogleExchangeRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class GoogleAuthController
{
    public function __construct(
        private readonly GoogleOAuthServiceInterface $googleService,
    ) {}

    /**
     * Menggantikan kode sekali pakai dari callback Google menjadi
     * { user, token } seperti login API yang ada; token tidak pernah
     * melewati URL karena frontend menukarkan kode ini lewat body.
     */
    public function exchange(GoogleExchangeRequest $request): JsonResponse
    {
        $result = $this->googleService->tukarkanKode(
            $request->validated('kode'),
            $request->input('device'),
        );

        return response()->json([
            'message' => 'Login berhasil',
            'data' => [
                'user' => new UserResource($result['user']->load('roles')->loadCount('diundang')),
                'token' => $result['token'],
                // Hanya akun Google yang baru dibuat dan belum memutuskan
                // soal kode referal; sisanya tidak pernah diberi opsi.
                'perlu_kode_referal' => $result['user']->referal_diisi_at === null,
            ],
        ]);
    }
}
