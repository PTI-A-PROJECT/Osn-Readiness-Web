<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(private LeaderboardService $service)
    {
    }

    // GET /leaderboard?tingkat=kabupaten|provinsi
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->top($this->tingkat($request)),
        ]);
    }

    // GET /leaderboard/me?tingkat=kabupaten|provinsi
    // User diambil dari login, frontend tidak mengirim user_id.
    public function me(Request $request): JsonResponse
    {
        $tingkat = $this->tingkat($request);

        // Anggota tidak mengerjakan pre-test, jadi pesan "kerjakan pre-test dulu"
        // dari service akan menyesatkan.
        $data = $request->user()->roles->contains('name', 'anggota')
            ? ['layak' => false, 'alasan' => 'Akun anggota tidak ikut peringkat latihan.']
            : $this->service->posisiSaya($tingkat, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function tingkat(Request $request): string
    {
        return $request->validate([
            'tingkat' => ['required', 'in:kabupaten,provinsi'],
        ])['tingkat'];
    }
}