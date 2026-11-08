<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnggotaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function anggota(string $nama, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['name' => $nama], $attributes));
        $user->assignRole('anggota');

        return $user;
    }

    public function test_admin_melihat_jumlah_pendaftar_dan_urutan_nama(): void
    {
        $a = $this->anggota('Anggota A');
        $b = $this->anggota('Anggota B');
        User::factory()->create(['direferensikan_oleh' => $a->id]);
        User::factory()->create(['direferensikan_oleh' => $a->id, 'is_active' => false]);
        $terhapus = User::factory()->create(['direferensikan_oleh' => $a->id]);
        $terhapus->delete();
        User::factory()->siswa()->create(['name' => 'Siswa', 'direferensikan_oleh' => $a->id]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())->getJson('/api/admin/anggota')->assertOk();
        $data = $response->json('data');
        $this->assertSame(['Anggota A', 'Anggota B'], array_column($data, 'name'));
        $this->assertSame(3, $data[0]['jumlah_diundang']);
        $this->assertSame(0, $data[1]['jumlah_diundang']);
        $this->assertNotContains('Siswa', array_column($data, 'name'));
    }

    public function test_akses_daftar_dibatasi_super_admin(): void
    {
        $this->getJson('/api/admin/anggota')->assertUnauthorized();
        $this->actingAs(User::factory()->siswa()->create())->getJson('/api/admin/anggota')->assertForbidden();
        $anggota = $this->anggota('Anggota');
        $this->actingAs($anggota)->getJson('/api/admin/anggota')->assertForbidden();
    }

    public function test_daftar_kosong_jika_role_anggota_dihapus(): void
    {
        Role::findByName('anggota', 'web')->delete();
        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson('/api/admin/anggota')->assertOk()->assertJsonPath('data', []);
    }
}
