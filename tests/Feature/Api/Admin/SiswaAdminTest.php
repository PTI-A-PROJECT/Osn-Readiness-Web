<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_admin_can_list_siswa(): void
    {
        $admin = $this->createAdmin();
        User::factory()->count(5)->create(['is_active' => true]);

        $response = $this->actingAs($admin)->getJson('/api/admin/siswa');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_can_paginate_siswa(): void
    {
        $admin = $this->createAdmin();
        User::factory()->count(20)->create(['is_active' => true]);

        $response = $this->actingAs($admin)->getJson('/api/admin/siswa?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_admin_can_show_siswa(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->getJson("/api/admin/siswa/{$siswa->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $siswa->id)
            ->assertJsonPath('data.email', $siswa->email);
    }

    public function test_admin_can_update_siswa(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->putJson("/api/admin/siswa/{$siswa->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $siswa->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_deactivate_siswa(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->postJson("/api/admin/siswa/{$siswa->id}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $siswa->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_deactivate_revokes_tokens(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        // Create a token for the siswa
        $token = $siswa->createToken('test-token');

        $this->actingAs($admin)->postJson("/api/admin/siswa/{$siswa->id}/deactivate");

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $siswa->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_inactive_siswa_not_listed(): void
    {
        $admin = $this->createAdmin();
        User::factory()->count(5)->create(['is_active' => true]);
        User::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($admin)->getJson('/api/admin/siswa');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_validation_error_on_update(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->putJson("/api/admin/siswa/{$siswa->id}", [
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/admin/siswa')->assertUnauthorized();
    }

    public function test_siswa_dilarang_mengakses_daftar_siswa(): void
    {
        $siswa = User::factory()->create(['is_active' => true]);
        $siswa->assignRole('siswa');

        $this->actingAs($siswa)
            ->getJson('/api/admin/siswa')
            ->assertForbidden();
    }

    public function test_admin_dapat_soft_delete_siswa(): void
    {
        $admin = $this->createAdmin();
        $siswa = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/siswa/{$siswa->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $siswa->id]);
    }

    public function test_password_tidak_ikut_dalam_respons(): void
    {
        $admin = $this->createAdmin();
        User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->getJson('/api/admin/siswa')
            ->assertOk()
            ->assertJsonMissing(['password' => 'password']);
    }
}
