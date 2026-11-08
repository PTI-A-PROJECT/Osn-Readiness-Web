<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnggotaKodeReferalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function anggota(string $nama, string $kode): User
    {
        $user = User::factory()->create(['name' => $nama, 'kode_referal' => $kode, 'nim' => fake()->unique()->numerify('##########')]);
        $user->assignRole('anggota');

        return $user;
    }

    private function daftar(string $kode): TestResponse
    {
        return $this->postJson('/api/auth/register', [
            'name' => 'Pendaftar', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password123', 'password_confirmation' => 'password123',
            'kode_referal' => $kode,
        ]);
    }

    public function test_perubahan_kode_menjaga_pendaftar_lama_dan_menerima_kode_baru_saja(): void
    {
        $anggota = $this->anggota('Achmad', 'OSNACHMAD');
        User::factory()->create(['direferensikan_oleh' => $anggota->id]);

        $this->actingAs($anggota)->putJson('/api/anggota/kode-referal', ['nama' => 'budi'])
            ->assertOk()->assertJsonPath('data.kode_referal', 'OSNBUDI')
            ->assertJsonPath('data.jumlah_diundang', 1);
        $this->assertSame(1, $anggota->fresh()->diundang()->count());
        $this->daftar('OSNACHMAD')->assertUnprocessable()->assertJsonValidationErrors('kode_referal');
        $this->daftar('OSNBUDI')->assertCreated();
    }

    public function test_nama_kode_memvalidasi_format_dan_bentrok(): void
    {
        $anggota = $this->anggota('Achmad', 'OSNACHMAD');
        foreach (['a', 'dua kata', 'ab-cd', 'abcdefghijklmnopq'] as $nama) {
            $this->actingAs($anggota)->putJson('/api/anggota/kode-referal', ['nama' => $nama])
                ->assertUnprocessable()->assertJsonValidationErrors('nama');
        }
        $this->anggota('Budi', 'OSNBUDI');
        $this->actingAs($anggota)->putJson('/api/anggota/kode-referal', ['nama' => 'budi'])
            ->assertUnprocessable()->assertJsonValidationErrors('nama');
    }

    public function test_nama_sama_tidak_mengubah_kode(): void
    {
        $anggota = $this->anggota('Achmad', 'OSNACHMAD');
        $this->actingAs($anggota)->putJson('/api/anggota/kode-referal', ['nama' => 'achmad'])
            ->assertOk()->assertJsonPath('data.kode_referal', 'OSNACHMAD');
    }

    public function test_perubahan_kode_hanya_untuk_anggota(): void
    {
        $this->putJson('/api/anggota/kode-referal', ['nama' => 'budi'])->assertUnauthorized();
        $siswa = User::factory()->siswa()->create();
        $this->actingAs($siswa)->putJson('/api/anggota/kode-referal', ['nama' => 'budi'])->assertForbidden();
    }
}
