<?php

namespace Tests\Feature\Api;

use App\Models\TingkatSeleksi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_register_creates_siswa_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Siswa Baru',
            'email' => 'siswa@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'is_active', 'tingkat_aktif_id', 'roles'], 'token'],
            ])
            ->assertJsonPath('data.user.is_active', true)
            ->assertJsonPath('data.user.roles.0', 'siswa');

        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'siswa@example.com',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $response->json('data.user.id'),
            'role_id' => $this->peranSiswa(),
        ]);
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'ada@example.com',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Siswa',
            'email' => 'bukan-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_email_yang_sudah_dihapus_bisa_dipakai_lagi(): void
    {
        User::factory()->create(['email' => 'lama@example.com'])->delete();

        $this->postJson('/api/auth/register', [
            'name' => 'Siswa Baru',
            'email' => 'lama@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_login_success_returns_token(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(1, PersonalAccessToken::count());
    }

    public function test_login_email_tidak_dikenal_dan_password_salah_balas_401_yang_sama(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

        $salahPassword = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password-salah',
        ]);

        $emailTidakDikenal = $this->postJson('/api/auth/login', [
            'email' => 'tidak@example.com',
            'password' => 'password',
        ]);

        foreach ([$salahPassword, $emailTidakDikenal] as $response) {
            $response->assertUnauthorized()
                ->assertJsonPath('kode', 'KREDENSIAL_TIDAK_VALID')
                ->assertJsonPath('message', 'Email atau password salah.');
        }

        $this->assertSame(
            $salahPassword->json('message'),
            $emailTidakDikenal->json('message'),
        );
    }

    public function test_login_akun_nonaktif_balas_403(): void
    {
        User::factory()->tidakAktif()->create([
            'email' => 'nonaktif@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'nonaktif@example.com',
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('kode', 'AKUN_TIDAK_AKTIF');
    }

    public function test_login_dibatasi_lima_kali_per_menit(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

        for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ])->assertOk();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertStatus(429);
    }

    public function test_login_tidak_membocorkan_keberadaan_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'tidak@example.com',
            'password' => 'password12345',
        ]);

        $response->assertUnauthorized()->assertJsonStructure(['message', 'kode', 'detail']);
    }

    public function test_me_returns_tingkat_aktif_id(): void
    {
        $tingkat = TingkatSeleksi::factory()->provinsi()->create();
        $user = User::factory()->create(['tingkat_aktif_id' => $tingkat->id]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.tingkat_aktif_id', $tingkat->id)
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'email', 'is_active', 'tingkat_aktif_id', 'roles']]);
    }

    public function test_me_tingkat_aktif_id_kosong_saat_siswa_belum_mulai_pretest(): void
    {
        $user = User::factory()->create(['tingkat_aktif_id' => null]);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tingkat_aktif_id', null);
    }

    public function test_me_dan_logout_tanpa_token_balas_401(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_logout_mencabut_hanya_token_yang_dipakai(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);

        $tokenSatu = $this->login('test@example.com')->json('data.token');
        $tokenDua = $this->login('test@example.com')->json('data.token');

        $this->assertSame(2, PersonalAccessToken::count());

        $this->withToken($tokenSatu)->postJson('/api/auth/logout')->assertOk();

        // Auth guard menyimpan user yang sudah ter-resolve, jadi harus dibuang
        // supaya request berikutnya benar-benar memverifikasi token lagi.
        $this->app['auth']->forgetGuards();

        $this->withToken($tokenSatu)->getJson('/api/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenDua)->getJson('/api/auth/me')->assertOk();

        $this->assertSame(1, PersonalAccessToken::count());
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_akun_yang_dinonaktifkan_langsung_ditolak(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => 'password']);
        $token = $this->login('test@example.com')->json('data.token');

        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Akun tidak aktif');
    }

    private function login(string $email): TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();
    }

    private function peranSiswa(): int
    {
        return Role::where('name', 'siswa')->value('id');
    }
}
