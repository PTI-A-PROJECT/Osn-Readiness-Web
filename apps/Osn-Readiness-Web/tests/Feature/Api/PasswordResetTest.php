<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordSiapOsn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_terdaftar_menerima_tautan_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'siswa@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'SISWA@EXAMPLE.COM',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordSiapOsn::class);
    }

    public function test_email_tidak_terdaftar_mendapat_respons_sama(): void
    {
        Notification::fake();
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'siswa-tidak-ada@example.com',
        ])->assertOk();

        $response->assertJsonPath('message', 'Jika email terdaftar, tautan pemulihan kata sandi akan dikirim.');
        Notification::assertNothingSent();
    }

    public function test_reset_sukses_mengganti_password_dan_mencabut_token_sesi(): void
    {
        $user = User::factory()->create(['email' => 'siswa@example.com', 'password' => 'password-lama']);
        $user->createToken('lama');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'SISWA@EXAMPLE.COM',
            'token' => $token,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'siswa@example.com']);
    }

    public function test_reset_menolak_token_tidak_valid(): void
    {
        User::factory()->create(['email' => 'siswa@example.com']);
        $this->postJson('/api/auth/reset-password', [
            'email' => 'siswa@example.com',
            'token' => 'token-palsu',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertStatus(422)->assertJsonPath('kode', 'RESET_TOKEN_TIDAK_VALID');
    }

    public function test_reset_memvalidasi_password_dan_konfirmasi(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'email' => 'siswa@example.com',
            'token' => 'token',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
