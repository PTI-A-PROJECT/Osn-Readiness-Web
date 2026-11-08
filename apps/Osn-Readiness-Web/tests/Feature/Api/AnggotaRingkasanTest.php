<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnggotaRingkasanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function anggota(string $nama): User
    {
        $user = User::factory()->create(['name' => $nama]);
        $user->assignRole('anggota');

        return $user;
    }

    private function undang(User $user, int $jumlah, bool $termasukNonaktif = false): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            User::factory()->create([
                'direferensikan_oleh' => $user->id,
                'is_active' => ! ($termasukNonaktif && $i === $jumlah - 1),
            ]);
        }
    }

    public function test_ringkasan_menghitung_peringkat_seri_dan_total_anggota(): void
    {
        $a = $this->anggota('Anggota A');
        $b = $this->anggota('Anggota B');
        $c = $this->anggota('Anggota C');
        $d = $this->anggota('Anggota D');
        $this->undang($a, 3, true);
        $terhapus = User::factory()->create(['direferensikan_oleh' => $a->id]);
        $terhapus->delete();
        $this->undang($b, 1);
        $this->undang($d, 1);
        $siswa = User::factory()->siswa()->create();
        $this->undang($siswa, 5);

        $this->actingAs($a)->getJson('/api/anggota/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah_referal', 3)->assertJsonPath('data.peringkat', 1)
            ->assertJsonPath('data.jumlah_anggota', 4)->assertJsonPath('data.total_referal', 5);
        $this->actingAs($b)->getJson('/api/anggota/ringkasan')->assertOk()->assertJsonPath('data.peringkat', 2);
        $this->actingAs($d)->getJson('/api/anggota/ringkasan')->assertOk()->assertJsonPath('data.peringkat', 2);
        $this->actingAs($c)->getJson('/api/anggota/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah_referal', 0)->assertJsonPath('data.peringkat', 4);

        $d->delete();
        $this->actingAs($a)->getJson('/api/anggota/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah_anggota', 3)->assertJsonPath('data.total_referal', 4);
    }

    public function test_ringkasan_hanya_dapat_diakses_anggota(): void
    {
        $this->getJson('/api/anggota/ringkasan')->assertUnauthorized();
        $this->actingAs(User::factory()->siswa()->create())->getJson('/api/anggota/ringkasan')->assertForbidden();
    }
}
