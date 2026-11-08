<?php

namespace App\Http\Controllers\Api\Dev;

use App\Http\Resources\SoalResource;
use App\Models\Soal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewSoalController
{
    public function show(Request $request, Soal $soal): JsonResponse
    {
        // Defense in depth jika controller dipanggil di luar route development.
        abort_unless(app()->environment('local'), 404);

        $soal->loadMissing('konteks');

        return response()->json([
            'message' => 'OK',
            'data' => (new SoalResource($soal))->toArray($request),
        ]);
    }
}
