<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\UpsertPembahasanRequest;
use App\Http\Resources\PembahasanResource;
use App\Models\Pembahasan;
use App\Models\Soal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PembahasanController
{
    public function show(Soal $soal): JsonResponse
    {
        Gate::authorize('view', $soal);

        $pembahasan = $soal->pembahasan;

        if (! $pembahasan) {
            return response()->json([
                'message' => 'Pembahasan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'OK',
            'data' => new PembahasanResource($pembahasan),
        ]);
    }

    public function store(UpsertPembahasanRequest $request, Soal $soal): JsonResponse
    {
        Gate::authorize('update', $soal);

        $pembahasan = $soal->pembahasan ?? new Pembahasan;
        $pembahasan->soal_id = $soal->id;
        $pembahasan->fill($request->validated());
        $pembahasan->save();

        return response()->json([
            'message' => 'Pembahasan berhasil disimpan',
            'data' => new PembahasanResource($pembahasan),
        ], 201);
    }

    public function update(UpsertPembahasanRequest $request, Soal $soal): JsonResponse
    {
        Gate::authorize('update', $soal);

        $pembahasan = $soal->pembahasan;

        if (! $pembahasan) {
            return response()->json([
                'message' => 'Pembahasan tidak ditemukan',
            ], 404);
        }

        $pembahasan->update($request->validated());

        return response()->json([
            'message' => 'Pembahasan berhasil diperbarui',
            'data' => new PembahasanResource($pembahasan),
        ]);
    }

    public function destroy(Soal $soal): JsonResponse
    {
        Gate::authorize('update', $soal);

        $pembahasan = $soal->pembahasan;

        if (! $pembahasan) {
            return response()->json([
                'message' => 'Pembahasan tidak ditemukan',
            ], 404);
        }

        $pembahasan->delete();

        return response()->json([
            'message' => 'Pembahasan berhasil dihapus',
        ]);
    }
}
