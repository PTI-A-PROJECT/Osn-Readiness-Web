<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\RiwayatHasilRepositoryInterface;
use App\Http\Requests\Riwayat\IndexRequest;
use App\Http\Resources\RiwayatHasilResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RiwayatController
{
    public function __construct(
        private readonly RiwayatHasilRepositoryInterface $riwayatRepository,
    ) {}

    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        return RiwayatHasilResource::collection(
            $this->riwayatRepository->untukSiswa(
                $request->user(),
                $request->input('jenis'),
                $request->integer('tingkat_id') === 0 ? null : $request->integer('tingkat_id'),
                $request->integer('per_page', 15),
            )
        );
    }
}
