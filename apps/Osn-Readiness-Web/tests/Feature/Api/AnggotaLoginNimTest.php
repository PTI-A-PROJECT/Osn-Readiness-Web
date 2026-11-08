<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnggotaLoginNimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function anggota(string $nim, array $attributes = []): User
    {
        $anggota = User::factory()->create(array_merge([
            'nim' => $nim,
            'email' => $nim.'@anggota.invalid',
            'password' => $nim,
        ], $attributes));
        $anggota->assignRole('anggota');

        return $anggota;
    }

    public function test_anggota_dapat_masuk_dengan_nim_dan_password_nim(): void
    {
        $anggota = $this->anggota('230533610158');
        $this->postJson('/api/auth/login', ['nim' => $anggota->nim, 'password' => $anggota->nim])
            ->assertOk()
            ->assertJsonPath('data.user.roles.0', 'anggota')
            ->assertJsonPath('data.user.nim', $anggota->nim)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_nim_dipakai_bila_email_juga_dikirim(): void
    {
        $anggota = $this->anggota('230533610158');
        $this->postJson('/api/auth/login', [
            'nim' => $anggota->nim,
            'email' => 'bukan-akun@example.com',
            'password' => $anggota->nim,
        ])->assertOk()->assertJsonPath('data.user.nim', $anggota->nim);
    }

    public function test_password_salah_dan_nim_tidak_dikenal_memiliki_pesan_yang_sama(): void
    {
        $this->anggota('230533610158');
        $salah = $this->postJson('/api/auth/login', ['nim' => '230533610158', 'password' => 'salah'])->assertUnauthorized();
        $tidakDikenal = $this->postJson('/api/auth/login', ['nim' => '230533699999', 'password' => 'salah'])->assertUnauthorized();
        $this->assertSame($salah->json('message'), $tidakDikenal->json('message'));
    }

    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        $this->anggota('230533610158', ['is_active' => false]);
        $this->postJson('/api/auth/login', ['nim' => '230533610158', 'password' => '230533610158'])->assertForbidden();
    }

    public function test_login_memvalidasi_nim_atau_email(): void
    {
        $this->postJson('/api/auth/login', ['nim' => 'abc', 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('nim');
        $this->postJson('/api/auth/login', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors(['nim', 'email']);
    }

    public function test_limiter_nim_membatasi_per_nim_dan_ip(): void
    {
        $a = $this->anggota('230533610158');
        $b = $this->anggota('230533605308');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['nim' => $a->nim, 'password' => 'salah'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['nim' => $a->nim, 'password' => 'salah'])->assertStatus(429);
        $this->postJson('/api/auth/login', ['nim' => $b->nim, 'password' => $b->nim])->assertOk();
    }
}
