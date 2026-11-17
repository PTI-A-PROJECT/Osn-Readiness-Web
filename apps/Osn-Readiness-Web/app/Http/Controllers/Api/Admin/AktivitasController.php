<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\AktivitasServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AktivitasController
{
    public function __construct(
        private readonly AktivitasServiceInterface $aktivitas,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Kosong = semua user. Ini dipakai halaman admin untuk melihat
            // aktivitas lintas akun tanpa harus memilih satu per satu.
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'kategori' => ['nullable', 'in:akun,belajar,admin'],
        ]);

        $timeline = $this->aktivitas->timeline(
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            isset($data['dari']) ? Carbon::parse($data['dari']) : null,
            isset($data['sampai']) ? Carbon::parse($data['sampai']) : null,
            $data['kategori'] ?? null,
        );

        return response()->json([
            'message' => 'OK',
            'data' => $timeline,
        ]);
    }

    public function detail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'aksi' => ['required', 'string', 'max:60'],
            'tanggal' => ['required', 'date'],
        ]);

        return response()->json([
            'message' => 'OK',
            'data' => $this->aktivitas->detail(
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                $data['aksi'],
                Carbon::parse($data['tanggal'])->toDateString(),
            ),
        ]);
    }

    public function analisis(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tingkat_id' => ['nullable', 'integer', 'exists:tingkat_seleksi,id'],
            'peruntukan' => ['nullable', 'in:pretest,latihan,simulasi'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'materi_id' => ['nullable', 'integer', 'exists:materi,id'],
        ]);

        return response()->json([
            'message' => 'OK',
            'data' => $this->aktivitas->analisis(
                isset($data['tingkat_id']) ? (int) $data['tingkat_id'] : null,
                $data['peruntukan'] ?? null,
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                isset($data['materi_id']) ? (int) $data['materi_id'] : null,
            ),
        ]);
    }
}
