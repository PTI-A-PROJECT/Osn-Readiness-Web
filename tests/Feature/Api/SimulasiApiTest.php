<?php

namespace Tests\Feature\Api;

use App\Models\Simulasi;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulasiApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private TingkatSeleksi $tingkat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('RolesAndPermissionsSeeder');

        $this->superAdmin = User::factory()->create(['email' => 'super@admin.com']);
        $this->superAdmin->assignRole('Super Admin');

        $this->tingkat = TingkatSeleksi::factory()->create();
    }

    public function test_can_list_simulasi(): void
    {
        Simulasi::factory()->count(2)->create(['tingkat_id' => $this->tingkat->id]);

        $this->actingAs($this->superAdmin)
            ->getJson('api/admin/simulasi')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_simulasi(): void
    {
        $data = [
            'tingkat_id' => $this->tingkat->id,
            'nama_simulasi' => 'Simulasi Minggu 1',
            'deskripsi' => 'Simulasi pertama',
            'jumlah_soal' => 30,
            'durasi_menit' => 120,
            'is_aktif' => true,
        ];

        $this->actingAs($this->superAdmin)
            ->postJson('api/admin/simulasi', $data)
            ->assertCreated()
            ->assertJsonPath('data.nama_simulasi', $data['nama_simulasi']);
    }

    public function test_can_update_simulasi(): void
    {
        $simulasi = Simulasi::factory()->create(['tingkat_id' => $this->tingkat->id]);

        $updateData = [
            'nama_simulasi' => 'Simulasi Updated',
            'deskripsi' => 'Updated description',
            'jumlah_soal' => 40,
            'durasi_menit' => 150,
            'is_aktif' => false,
        ];

        $this->actingAs($this->superAdmin)
            ->putJson("api/admin/simulasi/{$simulasi->id}", $updateData)
            ->assertOk()
            ->assertJsonPath('data.nama_simulasi', $updateData['nama_simulasi']);
    }

    public function test_can_delete_simulasi(): void
    {
        $simulasi = Simulasi::factory()->create(['tingkat_id' => $this->tingkat->id]);

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/admin/simulasi/{$simulasi->id}")
            ->assertOk();

        $this->assertDatabaseMissing('simulasi', ['id' => $simulasi->id]);
    }
}
