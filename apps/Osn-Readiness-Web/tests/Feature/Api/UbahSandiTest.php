<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UbahSandiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function siswa(): User
    {
        $user = User::factory()->create([
            'password' => 'SandiLama123',
            'is_active' => true,
        ]);
        $user->assignRole('siswa');

        return $user;
    }

    public function test_tamu_tidak_boleh_mengubah_sandi(): void
    {
        $this->putJson('/api/auth/password', [
            'current_password' => 'SandiLama123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertUnauthorized();
    }

    public function test_sandi_berhasil_diubah_dengan_sandi_lama_yang_benar(): void
    {
        $user = $this->siswa();

        $this->actingAs($user)->putJson('/api/auth/password', [
            'current_password' => 'SandiLama123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui');

        $this->assertTrue(Hash::check('SandiBaru123', $user->fresh()->password));
    }

    public function test_sandi_lama_yang_salah_ditolak(): void
    {
        $user = $this->siswa();

        $this->actingAs($user)->putJson('/api/auth/password', [
            'current_password' => 'SandiSalah',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('SandiLama123', $user->fresh()->password));
    }

    public function test_konfirmasi_sandi_tidak_cocok_ditolak(): void
    {
        $user = $this->siswa();

        $this->actingAs($user)->putJson('/api/auth/password', [
            'current_password' => 'SandiLama123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'BedaSekali123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('SandiLama123', $user->fresh()->password));
    }

    public function test_login_memakai_sandi_baru_setelah_diubah(): void
    {
        $user = $this->siswa();

        $this->actingAs($user)->putJson('/api/auth/password', [
            'current_password' => 'SandiLama123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'SandiBaru123',
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
    }
}
