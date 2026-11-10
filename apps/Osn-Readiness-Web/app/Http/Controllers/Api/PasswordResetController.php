<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    /**
     * Kirim tautan reset tanpa membocorkan apakah email terdaftar.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::sendResetLink([
            'email' => Str::lower(trim($validated['email'])),
        ]);

        return response()->json([
            'message' => 'Jika alamat email terdaftar, tautan pengaturan ulang kata sandi akan dikirim.',
        ]);
    }

    /**
     * Validasi token reset dan simpan kata sandi baru.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function ($user) use ($validated): void {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
                'kode' => 'RESET_TOKEN_TIDAK_VALID',
            ], 422);
        }

        return response()->json([
            'message' => 'Kata sandi berhasil diubah. Silakan masuk menggunakan kata sandi baru.',
        ]);
    }
}
