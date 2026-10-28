<?php

namespace Tests\Feature\Api;

use App\Models\GoogleLoginCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.frontend_url' => 'https://fe.example.com']);
    }

    private function googleUser(string $sub, string $email, bool $verified = true): SocialiteUser
    {
        $user = SocialiteUser::fake([
            'id' => $sub,
            'name' => 'Budi Google',
            'email' => $email,
        ]);

        // email_verified tidak dikirim oleh Socialite::fake secara bawaan;
        // tambahan ini meniru payload nyata Google untuk email terverifikasi.
        $raw = $user->user;
        $raw['email_verified'] = $verified;
        $user->setRaw($raw);

        return $user;
    }

    public function test_redirect_mengembalikan_redirect_response(): void
    {
        Socialite::fake('google');

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
    }

    public function test_callback_google_menolak_izin_dialihkan_ke_frontend(): void
    {
        $response = $this->get('/auth/google/callback?error=access_denied');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_batal');
    }

    public function test_callback_akun_baru_membuat_siswa_dengan_kode_sekali_pakai(): void
    {
        Socialite::fake('google', $this->googleUser('sub-google-1', 'budi@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'https://fe.example.com/oauth/google/selesai#code=',
            $response->headers->get('Location'),
        );

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'google_sub' => 'sub-google-1',
            'is_active' => true,
        ]);

        $user = User::query()->where('email', 'budi@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('siswa'));
        $this->assertDatabaseCount('google_login_codes', 1);

        $kode = Str::after($response->headers->get('Location'), '#code=');

        $exchange = $this->postJson('/api/auth/google/exchange', ['kode' => $kode]);

        $exchange->assertOk()
            ->assertJsonStructure(['message', 'data' => ['user' => ['id', 'email', 'roles'], 'token']])
            ->assertJsonPath('data.user.roles.0', 'siswa');

        $this->assertDatabaseHas('google_login_codes', [
            'user_id' => $user->id,
        ]);
        $this->assertNotNull(
            GoogleLoginCode::query()->where('user_id', $user->id)->first()->dipakai_pada,
        );
    }

    public function test_callback_email_lokal_sudah_ada_tak_digabung(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        Socialite::fake('google', $this->googleUser('sub-google-2', 'budi@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_taut');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('google_login_codes', 0);
    }

    public function test_callback_google_tertaut_melanjutkan_akun_lama(): void
    {
        $lama = User::factory()->create([
            'email' => 'budi@example.com',
            'google_sub' => 'sub-google-3',
        ]);
        $jumlahUserAwal = User::count();

        Socialite::fake('google', $this->googleUser('sub-google-3', 'budi@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('#code=', $response->headers->get('Location'));

        $this->assertSame($jumlahUserAwal, User::count());
        $this->assertDatabaseHas('google_login_codes', ['user_id' => $lama->id]);
    }

    public function test_callback_akun_admin_google_ditolak(): void
    {
        $admin = User::factory()->create(['google_sub' => 'sub-google-admin']);
        $admin->assignRole('Super Admin');

        Socialite::fake('google', $this->googleUser('sub-google-admin', 'admin@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_admin');
        $this->assertDatabaseCount('google_login_codes', 0);
    }

    public function test_callback_akun_nonaktif_google_ditolak(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'google_sub' => 'sub-google-nonaktif',
            'is_active' => false,
        ]);

        Socialite::fake('google', $this->googleUser('sub-google-nonaktif', 'budi@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_nonaktif');
        $this->assertDatabaseCount('google_login_codes', 0);
    }

    public function test_callback_email_belum_terverifikasi_ditolak(): void
    {
        Socialite::fake('google', $this->googleUser('sub-google-4', 'baru@example.com', verified: false));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_email_verifikasi');

        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
    }

    public function test_callback_gagal_layanan_google_dialihkan(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(
            new ConnectException(
                'gagal ke Google',
                new Request('GET', 'https://oauth2.googleapis.com/token'),
            ),
        );
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_gagal');
    }

    public function test_callback_state_tidak_cocok_dialihkan(): void
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('https://fe.example.com/oauth/google/selesai#error=google_state');
    }

    public function test_exchange_kedaluwarsa_ditolak(): void
    {
        $user = User::factory()->create();

        GoogleLoginCode::query()->create([
            'user_id' => $user->id,
            'kode_hash' => hash('sha256', 'kode-kedaluwarsa'),
            'kedaluwarsa_pada' => now()->subSeconds(1),
        ]);

        $response = $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-kedaluwarsa']);

        $response->assertUnauthorized()->assertJsonPath('kode', 'KODE_GOOGLE_TIDAK_VALID');
    }

    public function test_exchange_kode_hanya_sekali_pakai(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        GoogleLoginCode::query()->create([
            'user_id' => $user->id,
            'kode_hash' => hash('sha256', 'kode-aktif'),
            'kedaluwarsa_pada' => now()->addSeconds(60),
        ]);

        $pertama = $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-aktif']);
        $pertama->assertOk()->assertJsonPath('data.user.email', 'budi@example.com');

        $kedua = $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-aktif']);
        $kedua->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_exchange_kode_admin_ditolak(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        GoogleLoginCode::query()->create([
            'user_id' => $admin->id,
            'kode_hash' => hash('sha256', 'kode-admin'),
            'kedaluwarsa_pada' => now()->addSeconds(60),
        ]);

        $response = $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-admin']);

        $response->assertForbidden()->assertJsonPath('kode', 'GOOGLE_AKUN_ADMIN');
    }

    public function test_exchange_akun_nonaktif_ditolak(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        GoogleLoginCode::query()->create([
            'user_id' => $user->id,
            'kode_hash' => hash('sha256', 'kode-nonaktif'),
            'kedaluwarsa_pada' => now()->addSeconds(60),
        ]);

        $response = $this->postJson('/api/auth/google/exchange', ['kode' => 'kode-nonaktif']);

        $response->assertForbidden()->assertJsonPath('kode', 'AKUN_TIDAK_AKTIF');
    }

    public function test_exchange_validasi_tanpa_kode(): void
    {
        $response = $this->postJson('/api/auth/google/exchange', []);

        $response->assertUnprocessable();
    }
}
