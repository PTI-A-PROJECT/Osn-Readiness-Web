<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController
{
    public function kirimTautan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Jangan mengungkap apakah email terdaftar atau tidak.
        Password::sendResetLink([
            'email' => Str::lower(trim($data['email'])),
        ]);

        return response()->json([
            'message' => 'Jika email terdaftar, tautan pemulihan kata sandi akan dikirim.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $data['email'] = Str::lower(trim($data['email']));

        $status = Password::reset($data, function (User $user) use ($data): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();

            // Sesi API lama tidak boleh dapat dipakai kembali setelah reset.
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan reset baru.',
                'kode' => 'RESET_TOKEN_TIDAK_VALID',
            ], 422);
        }

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk kembali.',
        ]);
    }
}
