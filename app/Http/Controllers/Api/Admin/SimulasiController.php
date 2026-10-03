<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\BankSoalServiceInterface;
use App\Http\Requests\StoreSimulasiRequest;
use App\Http\Requests\UpdateSimulasiRequest;
use App\Http\Resources\SimulasiResource;
use App\Models\Simulasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SimulasiController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Simulasi::class);

        $perPage = (int) request('per_page', 15);

        return SimulasiResource::collection(
            Simulasi::query()
                ->with('tingkat')
                ->latest()
                ->paginate($perPage)
        );
    }

    public function show(Simulasi $simulasi): JsonResponse
    {
        Gate::authorize('view', $simulasi);

        $simulasi->load('tingkat');

        return response()->json([
            'message' => 'OK',
            'data' => new SimulasiResource($simulasi),
        ]);
    }

    public function store(StoreSimulasiRequest $request, BankSoalServiceInterface $bankSoal): JsonResponse
    {
        Gate::authorize('create', Simulasi::class);

        // Guard is_aktif: menyalakan simulasi hanya boleh bila bank soal
        // cukup untuk satu percobaan utuh (BE-19).
        $this->jagaIsAktif($request->tingkat_id, $request->integer('jumlah_soal'), $request->boolean('is_aktif'), $bankSoal);

        $simulasi = Simulasi::create($request->validated());
        $simulasi->load('tingkat');

        return response()->json([
            'message' => 'Simulasi berhasil ditambahkan',
            'data' => new SimulasiResource($simulasi),
        ], 201);
    }

    public function update(UpdateSimulasiRequest $request, Simulasi $simulasi, BankSoalServiceInterface $bankSoal): JsonResponse
    {
        Gate::authorize('update', $simulasi);

        $this->jagaIsAktif(
            $request->integer('tingkat_id') ?: (int) $simulasi->tingkat_id,
            $request->integer('jumlah_soal') ?: (int) $simulasi->jumlah_soal,
            $request->boolean('is_aktif'),
            $bankSoal,
        );

        $simulasi->update($request->validated());
        $simulasi->load('tingkat');

        return response()->json([
            'message' => 'Simulasi berhasil diperbarui',
            'data' => new SimulasiResource($simulasi),
        ]);
    }

    public function destroy(Simulasi $simulasi): JsonResponse
    {
        Gate::authorize('delete', $simulasi);

        $simulasi->delete();

        return response()->json([
            'message' => 'Simulasi berhasil dihapus',
        ]);
    }

    /**
     * Guard is_aktif: menyalakan simulasi hanya boleh bila bank soal cukup
     * untuk satu percobaan utuh (BE-19). Mematikan selalu boleh.
     *
     * @throws ValidationException
     */
    private function jagaIsAktif(int $tingkatId, int $jumlahSoal, bool $isAktif, BankSoalServiceInterface $bankSoal): void
    {
        if (! $isAktif) {
            return;
        }

        if (! $bankSoal->cukupUntukSimulasi($tingkatId, $jumlahSoal)) {
            throw ValidationException::withMessages([
                'is_aktif' => ['Bank soal tidak cukup untuk satu percobaan simulasi utuh.'],
            ]);
        }
    }
}
