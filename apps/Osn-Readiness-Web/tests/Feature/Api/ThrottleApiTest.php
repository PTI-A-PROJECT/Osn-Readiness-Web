<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ThrottleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_register_dibatasi_lima_kali_per_menit_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => 'Pendaftar '.$i,
                'email' => "pendaftar{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertCreated();
        }

        $this->postJson('/api/auth/register', [
            'name' => 'Pendaftar 6',
            'email' => 'pendaftar6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429);

        $this->assertDatabaseMissing('users', ['email' => 'pendaftar6@example.com']);
    }

    public function test_endpoint_terautentikasi_dibatasi_per_user(): void
    {
        $user = User::factory()->create();

        // Limiter produksi: 180 per menit, kunci id user (bukan IP) supaya
        // satu jaringan kampus tidak saling menghalangi.
        $request = Request::create('/api/auth/me', 'GET');
        $request->setUserResolver(fn () => $user);
        $limit = (RateLimiter::limiter('api'))($request);
        $this->assertSame(180, $limit->maxAttempts);
        $this->assertSame((string) $user->id, (string) $limit->key);

        // Batas diturunkan sementara agar tes tidak mengirim 181 request;
        // jalur middleware throttle:api tetap yang diuji.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(2)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
        $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
        $this->actingAs($user)->getJson('/api/auth/me')->assertStatus(429);
    }
}
