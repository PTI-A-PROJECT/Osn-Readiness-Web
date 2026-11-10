<?php

namespace Tests\Feature\Api\Admin;

use App\Contracts\Services\AktivitasServiceInterface;
use App\Models\LogAktivitas;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AktivitasAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function siswa(): User
    {
        $siswa = User::factory()->create(['is_active' => true]);
        $siswa->assignRole('siswa');

        return $siswa;
    }

    public function test_siswa_tidak_boleh_melihat_log_aktivitas(): void
    {
        $siswa = $this->siswa();

        $this->actingAs($siswa)
            ->getJson('/api/admin/aktivitas?user_id='.$siswa->id)
            ->assertForbidden();
    }

    public function test_admin_mendapat_timeline_dengan_grouping(): void
    {
        $admin = $this->admin();
        $siswa = $this->siswa();

        $aktivitas = app(AktivitasServiceInterface::class);
        $aktivitas->catat($siswa, 'akun.masuk', 'akun', ['metode' => 'email']);
        $aktivitas->catat($siswa, 'akun.masuk', 'akun', ['metode' => 'email']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/aktivitas?user_id='.$siswa->id);

        $response->assertOk();

        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertSame('akun.masuk', $data[0]['entri'][0]['aksi']);
        $this->assertSame(2, $data[0]['entri'][0]['kali']);
    }

    public function test_tanpa_user_id_menampilkan_aktivitas_semua_user(): void
    {
        $admin = $this->admin();
        $pertama = $this->siswa();
        $kedua = $this->siswa();

        $aktivitas = app(AktivitasServiceInterface::class);
        $aktivitas->catat($pertama, 'akun.masuk', 'akun');
        $aktivitas->catat($kedua, 'akun.keluar', 'akun');

        $response = $this->actingAs($admin)->getJson('/api/admin/aktivitas');

        $response->assertOk();

        $entri = collect($response->json('data'))
            ->flatMap(fn (array $hari): array => $hari['entri']);

        $this->assertEqualsCanonicalizing(
            [$pertama->id, $kedua->id],
            $entri->pluck('user_id')->all(),
        );

        $this->assertNotNull($entri->firstWhere('aksi', 'akun.masuk')['user_nama']);
    }

    public function test_detail_mengembalikan_baris_mentah(): void
    {
        $admin = $this->admin();
        $siswa = $this->siswa();

        app(AktivitasServiceInterface::class)->catat($siswa, 'akun.keluar', 'akun');

        $tanggal = now()->toDateString();

        $this->actingAs($admin)
            ->getJson('/api/admin/aktivitas/detail?user_id='.$siswa->id.'&aksi=akun.keluar&tanggal='.$tanggal)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_prune_menghapus_log_lebih_lama_dari_retensi(): void
    {
        $siswa = $this->siswa();

        LogAktivitas::create([
            'user_id' => $siswa->id,
            'aksi' => 'akun.masuk',
            'kategori' => 'akun',
            'terjadi_pada' => now()->subDays(100),
        ]);

        LogAktivitas::create([
            'user_id' => $siswa->id,
            'aksi' => 'akun.masuk',
            'kategori' => 'akun',
            'terjadi_pada' => now()->subDays(10),
        ]);

        $this->artisan('aktivitas:prune', ['--hari' => 90])->assertSuccessful();

        $this->assertDatabaseCount('log_aktivitas', 1);
    }
}
