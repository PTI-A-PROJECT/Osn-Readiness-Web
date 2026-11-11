<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\AnggotaServiceInterface;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnggotaController
{
    public function __construct(
        private readonly AnggotaServiceInterface $anggotaService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('lihatAnggota', User::class);

        $perPage = (int) request('per_page', 15);
        $urut = request()->validate(['urut' => ['nullable', 'in:terbanyak,tersedikit,tanpa']])['urut'] ?? null;

        return UserResource::collection($this->anggotaService->getAll($perPage, $urut));
    }
}
