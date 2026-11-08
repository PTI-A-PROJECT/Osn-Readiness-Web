<?php

namespace Tests\Feature\Api;

use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Pretest;
use App\Models\Quiz;
use App\Models\QuizPengerjaan;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LeaderboardAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Leaderboard di-cache 30 detik; bersihkan supaya data yang dibuat
        // di satu tes tidak tertukar dengan tes lain.
        Cache::flush();
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

    public function test_peringkat_dan_posisi_saya_memakai_data_yang_sama(): void
    {
        $tingkat = TingkatSeleksi::query()->firstOrFail();
        $siswa = User::factory()->siswa()->create();

        Pretest::factory()->selesai(80)->create([
            'user_id' => $siswa->id,
            'tingkat_id' => $tingkat->id,
        ]);

        $kompetensi = Kompetensi::factory()->create(['tingkat_id' => $tingkat->id]);
        $materi = Materi::factory()->create([
            'tingkat_id' => $tingkat->id,
            'kompetensi_id' => $kompetensi->id,
        ]);
        $quiz = Quiz::factory()->create(['materi_id' => $materi->id]);

        QuizPengerjaan::factory()->selesai(90)->create([
            'user_id' => $siswa->id,
            'quiz_id' => $quiz->id,
        ]);

        Cache::flush();

        $top = $this->actingAs($siswa)
            ->getJson('/api/leaderboard?tingkat=kabupaten')
            ->assertOk()
            ->json('data');

        $me = $this->actingAs($siswa)
            ->getJson('/api/leaderboard/me?tingkat=kabupaten')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $top['total_peserta']);
        $this->assertSame($top['total_peserta'], $me['total_peserta']);
        $this->assertSame($top['peringkat'][0]['peringkat'], $me['peringkat']);
        $this->assertSame((float) $top['peringkat'][0]['skor'], (float) $me['skor']);
        $this->assertSame(90.0, (float) $me['skor']);
    }
}
