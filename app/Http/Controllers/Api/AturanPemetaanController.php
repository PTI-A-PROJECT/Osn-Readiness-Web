<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateAturanPemetaanRequest;
use App\Http\Resources\AturanPemetaanResource;
use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AturanPemetaanController
{
    public function show(TingkatSeleksi $tingkat): JsonResponse
    {
        Gate::authorize('viewAny', AturanPemetaan::class);

        $aturan = AturanPemetaan::query()
            ->where('tingkat_id', $tingkat->id)
            ->first();

        if (! $aturan) {
            return response()->json([
                'message' => 'Aturan pemetaan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'OK',
            'data' => new AturanPemetaanResource($aturan),
        ]);
    }

    public function update(UpdateAturanPemetaanRequest $request, TingkatSeleksi $tingkat): JsonResponse
    {
        Gate::authorize('update', AturanPemetaan::class);

        $aturan = AturanPemetaan::query()
            ->where('tingkat_id', $tingkat->id)
            ->first();

        if (! $aturan) {
            return response()->json([
                'message' => 'Aturan pemetaan tidak ditemukan',
            ], 404);
        }

        $aturan->update($request->validated());

        return response()->json([
            'message' => 'Aturan pemetaan berhasil diperbarui',
            'data' => new AturanPemetaanResource($aturan),
        ]);
    }
}
