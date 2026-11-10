<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\AktivitasServiceInterface;
use App\Contracts\Services\SiswaServiceInterface;
use App\Http\Requests\Admin\Siswa\UpdateSiswaRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SiswaController
{
    public function __construct(
        private readonly SiswaServiceInterface $siswaService,
        private readonly AktivitasServiceInterface $aktivitas,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $perPage = (int) request('per_page', 15);

        return UserResource::collection($this->siswaService->getAll($perPage));
    }

    public function show(User $siswa): JsonResponse
    {
        Gate::authorize('view', $siswa);

        return response()->json([
            'message' => 'OK',
            'data' => new UserResource($this->siswaService->getById($siswa->id)->load('roles')),
        ]);
    }

    public function update(UpdateSiswaRequest $request, User $siswa): JsonResponse
    {
        Gate::authorize('update', $siswa);

        $updated = $this->siswaService->update($siswa->id, $request->validated());

        $this->aktivitas->catat($updated, 'siswa.diubah_admin', 'admin', [
            'field' => array_keys($request->validated()),
            'oleh' => $request->user()?->name,
        ], User::class, (int) $updated->getKey());

        return response()->json([
            'message' => 'Siswa berhasil diperbarui',
            'data' => new UserResource($updated->load('roles')),
        ]);
    }

    public function deactivate(Request $request, User $siswa): JsonResponse
    {
        Gate::authorize('deactivate', $siswa);

        $updated = $this->siswaService->deactivate($siswa->id);

        $this->aktivitas->catat($updated, 'akun.dinonaktifkan', 'admin', [
            'oleh' => $request->user()?->name,
        ]);

        return response()->json([
            'message' => 'Siswa berhasil dinonaktifkan',
            'data' => new UserResource($updated->load('roles')),
        ]);
    }

    public function destroy(Request $request, User $siswa): JsonResponse
    {
        Gate::authorize('delete', $siswa);

        $this->aktivitas->catat($siswa, 'akun.dihapus', 'admin', [
            'oleh' => $request->user()?->name,
            'email' => $siswa->email,
        ]);

        $this->siswaService->delete($siswa->id);

        return response()->json([
            'message' => 'Siswa berhasil dihapus',
        ]);
    }
}
