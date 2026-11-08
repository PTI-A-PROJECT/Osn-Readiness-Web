<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\AnggotaServiceInterface;
use App\Http\Requests\Auth\UbahKodeReferalRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnggotaPanelController
{
    public function __construct(
        private readonly AnggotaServiceInterface $anggotaService,
    ) {}

    public function ringkasan(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'OK',
            'data' => $this->anggotaService->ringkasan($request->user()),
        ]);
    }

    public function ubahKode(UbahKodeReferalRequest $request): JsonResponse
    {
        $user = $this->anggotaService->ubahNamaKode($request->user(), $request->validated('nama'));

        return response()->json([
            'message' => 'Kode referal diperbarui',
            'data' => new UserResource($user),
        ]);
    }
}
