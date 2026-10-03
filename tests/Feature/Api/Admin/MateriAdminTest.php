<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MateriAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_can_list_materi(): void
    {
        $admin = $this->createAdmin();
        Materi::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/materi');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_filter_materi_by_kompetensi(): void
    {
        $admin = $this->createAdmin();
        $kompetensi1 = Kompetensi::factory()->create();
        $kompetensi2 = Kompetensi::factory()->create();

        Materi::factory()->count(2)->create(['kompetensi_id' => $kompetensi1->id]);
        Materi::factory()->create(['kompetensi_id' => $kompetensi2->id]);

        $response = $this->actingAs($admin)->getJson("/api/admin/materi?kompetensi_id={$kompetensi1->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_materi(): void
    {
        $admin = $this->createAdmin();
        $tingkat = TingkatSeleksi::factory()->create();
        $kompetensi = Kompetensi::factory()->create(['tingkat_id' => $tingkat->id]);

        $response = $this->actingAs($admin)->postJson('/api/admin/materi', [
            'tingkat_id' => $tingkat->id,
            'kompetensi_id' => $kompetensi->id,
            'urutan' => 1,
            'judul' => 'Materi Baru',
            'deskripsi' => 'Deskripsi materi',
            'isi_materi' => 'Isi materi lengkap',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.judul', 'Materi Baru');
    }

    public function test_admin_can_update_materi(): void
    {
        $admin = $this->createAdmin();
        $materi = Materi::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/materi/{$materi->id}", [
            'judul' => 'Updated Materi',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.judul', 'Updated Materi');
    }

    public function test_admin_can_upload_materi_image(): void
    {
        $admin = $this->createAdmin();
        $materi = Materi::factory()->create();

        $file = UploadedFile::fake()->image('materi.png', 640, 480);

        $response = $this->actingAs($admin)->postJson(
            "/api/admin/materi/{$materi->id}/upload-image",
            ['gambar' => $file]
        );

        $response->assertOk();
        Storage::disk('public')->assertExists($materi->fresh()->gambar);
    }

    public function test_admin_cannot_upload_non_image(): void
    {
        $admin = $this->createAdmin();
        $materi = Materi::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($admin)->postJson(
            "/api/admin/materi/{$materi->id}/upload-image",
            ['gambar' => $file]
        );

        $response->assertUnprocessable();
    }
}
