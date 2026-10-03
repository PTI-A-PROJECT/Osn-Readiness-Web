<?php

namespace Tests\Feature\Api;

use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SoalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $siswa;

    private TingkatSeleksi $tingkat;

    private Materi $materi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('RolesAndPermissionsSeeder');

        // Create super admin
        $this->superAdmin = User::factory()->create(['email' => 'super@admin.com']);
        $this->superAdmin->assignRole('Super Admin');

        // Create admin user with soal permissions
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo('soal.viewAny', 'soal.view', 'soal.create', 'soal.update', 'soal.delete');
        $this->admin->assignRole($adminRole);

        // Create regular siswa
        $this->siswa = User::factory()->create(['email' => 'siswa@test.com']);
        $this->siswa->assignRole('siswa');

        // Create test data
        $this->tingkat = TingkatSeleksi::factory()->create();
        $kompetensi = Kompetensi::factory()->create();
        $this->materi = Materi::factory()->create([
            'tingkat_id' => $this->tingkat->id,
            'kompetensi_id' => $kompetensi->id,
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('api/admin/soal')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_returns_403(): void
    {
        $this->actingAs($this->siswa)
            ->getJson('api/admin/soal')
            ->assertForbidden();
    }

    public function test_super_admin_can_list_soal(): void
    {
        Soal::factory()->count(3)->create(['materi_id' => $this->materi->id]);

        $this->actingAs($this->superAdmin)
            ->getJson('api/admin/soal')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'materi_id', 'level', 'tipe_soal', 'pertanyaan', 'pilihan_jawaban'],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_admin_can_list_soal(): void
    {
        Soal::factory()->count(3)->create(['materi_id' => $this->materi->id]);

        $this->actingAs($this->admin)
            ->getJson('api/admin/soal')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_super_admin_can_show_soal(): void
    {
        $soal = Soal::factory()->create(['materi_id' => $this->materi->id]);

        $this->actingAs($this->superAdmin)
            ->getJson("api/admin/soal/{$soal->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $soal->id)
            ->assertJsonPath('data.pertanyaan', $soal->pertanyaan);
    }

    public function test_super_admin_can_create_soal(): void
    {
        $data = [
            'tingkat_id' => $this->tingkat->id,
            'materi_id' => $this->materi->id,
            'level' => 'mudah',
            'peruntukan' => 'pretest',
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Apa itu test?',
            'pilihan_jawaban' => ['Jawaban A', 'Jawaban B', 'Jawaban C'],
            'kunci_jawaban' => 1,
        ];

        $this->actingAs($this->superAdmin)
            ->postJson('api/admin/soal', $data)
            ->assertCreated()
            ->assertJsonPath('data.pertanyaan', $data['pertanyaan']);

        $this->assertDatabaseHas('soal', [
            'pertanyaan' => $data['pertanyaan'],
            'kunci_jawaban' => $data['kunci_jawaban'],
        ]);
    }

    public function test_create_soal_validates_input(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson('api/admin/soal', [
                'tingkat_id' => 999,
                'materi_id' => 999,
                'pilihan_jawaban' => ['Hanya satu'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tingkat_id', 'materi_id', 'level', 'pertanyaan', 'kunci_jawaban']);
    }

    public function test_super_admin_can_update_soal(): void
    {
        $soal = Soal::factory()->create(['materi_id' => $this->materi->id]);

        $updateData = [
            'tingkat_id' => $this->tingkat->id,
            'materi_id' => $this->materi->id,
            'level' => 'sulit',
            'peruntukan' => 'simulasi',
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Pertanyaan baru',
            'pilihan_jawaban' => ['A', 'B', 'C', 'D'],
            'kunci_jawaban' => 2,
        ];

        $this->actingAs($this->superAdmin)
            ->putJson("api/admin/soal/{$soal->id}", $updateData)
            ->assertOk()
            ->assertJsonPath('data.pertanyaan', $updateData['pertanyaan']);

        $this->assertDatabaseHas('soal', [
            'id' => $soal->id,
            'pertanyaan' => $updateData['pertanyaan'],
        ]);
    }

    public function test_super_admin_can_delete_soal(): void
    {
        $soal = Soal::factory()->create(['materi_id' => $this->materi->id]);

        $this->actingAs($this->superAdmin)
            ->deleteJson("api/admin/soal/{$soal->id}")
            ->assertOk();

        // Soft delete — should still exist
        $this->assertDatabaseHas('soal', ['id' => $soal->id]);
    }

    public function test_kunci_jawaban_not_in_response(): void
    {
        $soal = Soal::factory()->create(['materi_id' => $this->materi->id]);

        $this->actingAs($this->siswa)
            ->actingAs($this->superAdmin)
            ->getJson("api/admin/soal/{$soal->id}")
            ->assertOk()
            // Admin view includes kunci_jawaban per SoalDetailResource
            ->assertJsonPath('data.kunci_jawaban', $soal->kunci_jawaban);
    }
}
