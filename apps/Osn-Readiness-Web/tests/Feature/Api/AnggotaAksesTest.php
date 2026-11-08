<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnggotaAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_anggota_ditolak_dari_fitur_belajar_dan_dapat_melihat_profilnya(): void
    {
        $anggota = User::factory()->create(['nim' => '230533610158', 'kode_referal' => 'OSNACHMAD']);
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)->getJson('/api/dashboard')
            ->assertForbidden()->assertJsonPath('message', 'Akun anggota tidak dapat memakai fitur belajar.');
        $this->actingAs($anggota)->getJson('/api/riwayat')
            ->assertForbidden()->assertJsonPath('message', 'Akun anggota tidak dapat memakai fitur belajar.');
        $this->actingAs($anggota)->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('data.roles.0', 'anggota')
            ->assertJsonPath('data.kode_referal', 'OSNACHMAD')
            ->assertJsonPath('data.jumlah_diundang', 0);
    }
}
