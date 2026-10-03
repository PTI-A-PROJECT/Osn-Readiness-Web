<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\StoreLatihanRequest;
use App\Http\Requests\UpdateLatihanRequest;
use App\Http\Resources\LatihanResource;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LatihanController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Quiz::class);

        $perPage = (int) request('per_page', 15);

        return LatihanResource::collection(
            Quiz::query()
                ->with('materi')
                ->latest()
                ->paginate($perPage)
        );
    }

    public function show(Quiz $quiz): JsonResponse
    {
        Gate::authorize('view', $quiz);

        $quiz->load('materi');

        return response()->json([
            'message' => 'OK',
            'data' => new LatihanResource($quiz),
        ]);
    }

    public function store(StoreLatihanRequest $request): JsonResponse
    {
        Gate::authorize('create', Quiz::class);

        $quiz = Quiz::create($request->validated());
        $quiz->load('materi');

        return response()->json([
            'message' => 'Latihan berhasil ditambahkan',
            'data' => new LatihanResource($quiz),
        ], 201);
    }

    public function update(UpdateLatihanRequest $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        $quiz->update($request->validated());
        $quiz->load('materi');

        return response()->json([
            'message' => 'Latihan berhasil diperbarui',
            'data' => new LatihanResource($quiz),
        ]);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        Gate::authorize('delete', $quiz);

        $quiz->delete();

        return response()->json([
            'message' => 'Latihan berhasil dihapus',
        ]);
    }
}
