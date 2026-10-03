<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Guards\SoalGuard;
use App\Http\Requests\StoreSoalRequest;
use App\Http\Requests\UpdateSoalRequest;
use App\Http\Resources\SoalDetailResource;
use App\Models\Soal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SoalController
{
    public function __construct(
        private readonly SoalRepositoryInterface $soalRepository,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Soal::class);

        $perPage = (int) request('per_page', 15);

        return SoalDetailResource::collection(
            $this->soalRepository->paginasiAdmin($perPage)
        );
    }

    public function show(Soal $soal): JsonResponse
    {
        Gate::authorize('view', $soal);

        $soal->load('tingkat', 'materi', 'konteks', 'pembahasan');

        return response()->json([
            'message' => 'OK',
            'data' => new SoalDetailResource($soal),
        ]);
    }

    public function store(StoreSoalRequest $request): JsonResponse
    {
        Gate::authorize('create', Soal::class);

        $data = $request->validated();

        // Handle file upload
        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')->store('soal', 'public');
        }

        $soal = Soal::create($data);
        $soal->load('tingkat', 'materi', 'konteks', 'pembahasan');

        return response()->json([
            'message' => 'Soal berhasil ditambahkan',
            'data' => new SoalDetailResource($soal),
        ], 201);
    }

    public function update(UpdateSoalRequest $request, Soal $soal): JsonResponse
    {
        Gate::authorize('update', $soal);

        SoalGuard::validateUpdate($soal, $request->validated());

        $data = SoalGuard::filterForUpdate($soal, $request->validated());

        // Handle file upload
        if ($request->hasFile('gambar')) {
            if ($soal->gambar) {
                Storage::disk('public')->delete($soal->gambar);
            }
            $data['gambar'] = $request->file('gambar')->store('soal', 'public');
        }

        $soal->update($data);
        $soal->load('tingkat', 'materi', 'konteks', 'pembahasan');

        return response()->json([
            'message' => 'Soal berhasil diperbarui',
            'data' => new SoalDetailResource($soal),
        ]);
    }

    public function destroy(Soal $soal): JsonResponse
    {
        Gate::authorize('delete', $soal);

        // Delete associated image
        if ($soal->gambar) {
            Storage::disk('public')->delete($soal->gambar);
        }

        // SoftDelete — soal tetap bisa diakses untuk review pengerjaan yang sudah selesai
        $soal->delete();

        return response()->json([
            'message' => 'Soal berhasil dihapus',
        ]);
    }
}
