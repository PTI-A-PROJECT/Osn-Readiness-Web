<?php

namespace Tests\Feature\Api;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AturanPemetaanApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private TingkatSeleksi $tingkat;

    private AturanPemetaan $aturan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('RolesAndPermissionsSeeder');

        $this->superAdmin = User::factory()->create(['email' => 'super@admin.com']);
        $this->superAdmin->assignRole('Super Admin');

        $this->tingkat = TingkatSeleksi::factory()->create();
        $this->aturan = AturanPemetaan::factory()->create(['tingkat_id' => $this->tingkat->id]);
    }

    public function test_can_show_aturan_pemetaan(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson("api/admin/tingkat/{$this->tingkat->id}/aturan-pemetaan")
            ->assertOk()
            ->assertJsonPath('data.tingkat_id', $this->tingkat->id);
    }

    public function test_show_aturan_pemetaan_not_found(): void
    {
        $tingkatBaru = TingkatSeleksi::factory()->create();

        $this->actingAs($this->superAdmin)
            ->getJson("api/admin/tingkat/{$tingkatBaru->id}/aturan-pemetaan")
            ->assertNotFound();
    }

    public function test_can_update_aturan_pemetaan(): void
    {
        $updateData = [
            'bobot_pretest' => 30,
            'persen_pretest_mudah' => 25,
            'persen_pretest_sedang' => 50,
            'persen_pretest_sulit' => 25,
            'passing_grade_pretest' => 60,
            'bobot_simulasi' => 70,
            'persen_simulasi_mudah' => 30,
            'persen_simulasi_sedang' => 40,
            'persen_simulasi_sulit' => 30,
            'passing_grade_simulasi' => 70,
            'latihan_min_nilai' => 70,
            'pretest_jumlah_soal' => 30,
            'pretest_min_soal_per_materi' => 1,
            'simulasi_maks_percobaan' => 3,
            'jumlah_materi_wajib' => 10,
        ];

        $this->actingAs($this->superAdmin)
            ->putJson("api/admin/tingkat/{$this->tingkat->id}/aturan-pemetaan", $updateData)
            ->assertOk()
            ->assertJsonPath('data.bobot_pretest', $updateData['bobot_pretest']);

        $this->assertDatabaseHas('aturan_pemetaan', [
            'tingkat_id' => $this->tingkat->id,
            'bobot_pretest' => $updateData['bobot_pretest'],
        ]);
    }

    public function test_update_aturan_pemetaan_validates_numeric(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson("api/admin/tingkat/{$this->tingkat->id}/aturan-pemetaan", [
                'bobot_pretest' => 'bukan angka',
            ])
            ->assertUnprocessable();
    }
}
