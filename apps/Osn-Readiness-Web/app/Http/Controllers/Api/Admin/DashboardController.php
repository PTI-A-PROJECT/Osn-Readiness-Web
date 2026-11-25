<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\DashboardAdminServiceInterface;
use App\Http\Requests\Admin\Dashboard\RingkasanDashboardRequest;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function __construct(
        private readonly DashboardAdminServiceInterface $dashboardService,
    ) {}

    public function ringkasan(RingkasanDashboardRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'OK',
            'data' => $this->dashboardService->ringkasan($request->dari(), $request->sampai()),
        ]);
    }
}
