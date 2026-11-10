<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_notification_for_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'siswa@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'SISWA@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Jika alamat email terdaftar, tautan pengaturan ulang kata sandi akan dikirim.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_disclose_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'tidak-ada@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Jika alamat email terdaftar, tautan pengaturan ulang kata sandi akan dikirim.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_consumes_token(): void
    {
        $user = User::factory()->create([
            'email' => 'siswa@example.com',
            'password' => 'password-lama',
        ]);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'SISWA@example.com',
            'token' => $token,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diubah. Silakan masuk menggunakan kata sandi baru.');

        $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'siswa@example.com']);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'siswa@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'siswa@example.com',
            'token' => 'token-tidak-valid',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertUnprocessable()
            ->assertJsonPath('kode', 'RESET_TOKEN_TIDAK_VALID');
    }

    public function test_reset_password_validates_confirmation_and_minimum_length(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'email' => 'siswa@example.com',
            'token' => 'token',
            'password' => 'short',
            'password_confirmation' => 'berbeda',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
