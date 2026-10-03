<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\SyaratSimulasiServiceInterface;
use App\Http\Resources\SyaratSimulasiResource;
use App\Models\TingkatSeleksi;
use Illuminate\Http\Request;

class SyaratSimulasiController
{
    public function __construct(
        private readonly SyaratSimulasiServiceInterface $syaratSimulasiService,
    ) {}

    public function show(Request $request, TingkatSeleksi $tingkat): SyaratSimulasiResource
    {
        return new SyaratSimulasiResource(
            $this->syaratSimulasiService->periksa($request->user(), $tingkat)
        );
    }
}
