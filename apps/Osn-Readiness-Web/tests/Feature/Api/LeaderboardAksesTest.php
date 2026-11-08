<?php

namespace Tests\Feature\Api;

use App\Models\TingkatSeleksi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        TingkatSeleksi::factory()->kabupaten()->create();
    }

    public function test_semua_akun_yang_login_dapat_melihat_leaderboard(): void
    {
        $anggota = User::factory()->create();
        $anggota->assignRole('anggota');

        foreach ([User::factory()->siswa()->create(), $anggota, User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)->getJson('/api/leaderboard?tingkat=kabupaten')
                ->assertOk()
                ->assertJsonPath('data.tingkat', 'kabupaten');
        }
    }

    public function test_anggota_mendapat_alasan_khusus_di_posisi_saya(): void
    {
        $anggota = User::factory()->create();
        $anggota->assignRole('anggota');

        $this->actingAs($anggota)->getJson('/api/leaderboard/me?tingkat=kabupaten')
            ->assertOk()
            ->assertJsonPath('data.layak', false)
            ->assertJsonPath('data.alasan', 'Akun anggota tidak ikut peringkat latihan.');
    }

    public function test_leaderboard_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/leaderboard?tingkat=kabupaten')->assertUnauthorized();
    }
}
