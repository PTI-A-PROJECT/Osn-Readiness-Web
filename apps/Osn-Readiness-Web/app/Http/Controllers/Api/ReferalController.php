<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\KodeReferal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferalController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Kode referal hanya bisa diisi sekali oleh akun Google baru, baik diisi
     * maupun dilewati (kode kosong). Setelah itu referal_diisi_at terisi dan
     * permintaan berikutnya ditolak.
     */
    public function simpan(Request $request): JsonResponse
    {
        $request->validate([
            'kode_referal' => ['nullable', 'string', 'max:255'],
        ]);

        $kode = KodeReferal::normalisasi($request->input('kode_referal'));

        /** @var User $user */
        $user = $request->user();

        $diperbarui = DB::transaction(function () use ($user, $kode): User {
            // Kunci baris akun agar dua permintaan bersamaan tidak bisa sama-
            // sama lolos pemeriksaan referal_diisi_at lalu menulis dua kali.
            $terkunci = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($terkunci->referal_diisi_at !== null) {
                throw ValidationException::withMessages([
                    'kode_referal' => ['Kode referal sudah pernah diisi atau dilewati.'],
                ]);
            }

            $pemberi = null;

            if ($kode !== null) {
                $pemberi = $this->userRepository->findByKodeReferal($kode);

                if ($pemberi === null) {
                    throw ValidationException::withMessages([
                        'kode_referal' => ['Kode referal tidak ditemukan.'],
                    ]);
                }

                if ($pemberi->id === $terkunci->id) {
                    throw ValidationException::withMessages([
                        'kode_referal' => ['Kode referal milik sendiri tidak bisa dipakai.'],
                    ]);
                }
            }

            $terkunci->forceFill([
                'direferensikan_oleh' => $pemberi?->id,
                'referal_diisi_at' => now(),
            ])->save();

            return $terkunci;
        });

        return response()->json([
            'message' => $diperbarui->direferensikan_oleh
                ? 'Kode referal berhasil disimpan'
                : 'Kode referal dilewati',
            'data' => [
                'user' => new UserResource($diperbarui->load('roles')->loadCount('diundang')),
            ],
        ]);
    }
}
