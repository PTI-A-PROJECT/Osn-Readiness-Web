<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\SimulasiServiceInterface;
use App\Http\Resources\SimulasiPengerjaanResource;
use App\Models\Simulasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SimulasiController
{
    public function __construct(
        private readonly SimulasiServiceInterface $simulasiService,
    ) {}

    public function index(Request $request)
    {
        $tingkatId = $request->query('tingkat_id', $request->user()->tingkat_aktif_id);

        if ($tingkatId === null || ! is_numeric($tingkatId)) {
            throw ValidationException::withMessages([
                'tingkat_id' => ['Tingkat belum diketahui: kirim tingkat_id atau selesaikan pre-test dulu.'],
            ]);
        }

        return response()->json([
            'message' => 'OK',
            'data' => $this->simulasiService->daftar($request->user(), (int) $tingkatId),
        ]);
    }

    public function mulai(Request $request, Simulasi $simulasi): JsonResponse
    {
        $dimulai = $this->simulasiService->mulai($request->user(), $simulasi);

        // Percobaan baru dibalas 201; yang sedang berjalan dikembalikan
        // ulang dengan 200 supaya siswa menyambung, bukan mengulang.
        return response()->json([
            'message' => 'OK',
            'data' => new SimulasiPengerjaanResource($dimulai),
        ], $dimulai->baru ? 201 : 200);
    }
}
