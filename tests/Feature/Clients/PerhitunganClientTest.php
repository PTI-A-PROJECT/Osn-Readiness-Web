<?php

namespace Tests\Feature\Clients;

use App\Clients\PerhitunganClient;
use App\Exceptions\PerhitunganKonfigurasiException;
use App\Exceptions\PerhitunganTidakTersediaException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PerhitunganClientTest extends TestCase
{
    protected PerhitunganClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the HTTP client
        Http::preventStrayRequests();

        // Set required config
        config([
            'services.perhitungan' => [
                'url' => 'http://localhost:8001',
                'token' => 'test-token-123',
                'timeout' => 5,
                'retry' => 2,
            ],
        ]);

        $this->client = app(PerhitunganClient::class);
    }

    /** @test */
    public function hitungNilai_dengan_respons_valid_mengembalikan_hasil_benar(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([
                'nilai' => 72.5,
                'benar' => 18,
                'salah' => 2,
                'kosong' => 0,
            ], 200),
        ]);

        $answers = [
            ['soalId' => 1, 'jawaban' => 'a'],
            ['soalId' => 2, 'jawaban' => 'b'],
        ];

        $result = $this->client->hitungNilai(1, $answers);

        $this->assertEquals(72.5, $result['nilai']);
        $this->assertEquals(18, $result['benar']);
        $this->assertEquals(2, $result['salah']);
        $this->assertEquals(0, $result['kosong']);
    }

    /** @test */
    public function hitungNilai_mengirim_header_internal_token(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([
                'nilai' => 0,
                'benar' => 0,
                'salah' => 0,
                'kosong' => 0,
            ], 200),
        ]);

        $this->client->hitungNilai(1, []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Internal-Token', 'test-token-123');
        });
    }

    /** @test */
    public function hitungNilai_dengan_respons_500_melempar_tidak_tersedia(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([], 500),
        ]);

        $this->expectException(PerhitunganTidakTersediaException::class);

        $this->client->hitungNilai(1, []);
    }

    /** @test */
    public function hitungNilai_dengan_respons_503_melempar_tidak_tersedia(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([], 503),
        ]);

        $this->expectException(PerhitunganTidakTersediaException::class);

        $this->client->hitungNilai(1, []);
    }

    /** @test */
    public function hitungNilai_dengan_respons_422_melempar_konfigurasi_error(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([
                'error' => 'Invalid request format',
            ], 422),
        ]);

        $this->expectException(PerhitunganKonfigurasiException::class);

        $this->client->hitungNilai(1, []);
    }

    /** @test */
    public function hitungNilai_dengan_respons_403_melempar_konfigurasi_error(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([
                'error' => 'Unauthorized token',
            ], 403),
        ]);

        $this->expectException(PerhitunganKonfigurasiException::class);

        $this->client->hitungNilai(1, []);
    }

    /** @test */
    public function hitungNilai_dengan_field_missing_melempar_konfigurasi_error(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/penilaian' => Http::response([
                'nilai' => 72.5,
                'benar' => 18,
                // 'salah' dan 'kosong' missing
            ], 200),
        ]);

        $this->expectException(PerhitunganKonfigurasiException::class);

        $this->client->hitungNilai(1, []);
    }

    /** @test */
    public function hitungRanking_dengan_respons_valid(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/ranking' => Http::response([
                ['rank' => 1, 'userId' => 5, 'nilai' => 95.0],
                ['rank' => 2, 'userId' => 3, 'nilai' => 88.5],
                ['rank' => 3, 'userId' => 7, 'nilai' => 75.0],
            ], 200),
        ]);

        $nilaiByUser = [5 => 95.0, 3 => 88.5, 7 => 75.0];

        $result = $this->client->hitungRanking(1, $nilaiByUser);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['rank']);
        $this->assertEquals(5, $result[0]['userId']);
        $this->assertEquals(95.0, $result[0]['nilai']);
    }

    /** @test */
    public function matchSoalToSiswa_dengan_respons_valid(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/match-soal' => Http::response([
                ['soalId' => 1, 'kompetensiId' => 5, 'tingkatKesulitan' => 'mudah'],
                ['soalId' => 3, 'kompetensiId' => 6, 'tingkatKesulitan' => 'sedang'],
                ['soalId' => 7, 'kompetensiId' => 5, 'tingkatKesulitan' => 'sulit'],
            ], 200),
        ]);

        $permintaan = new \App\DTOs\PermintaanSoal(
            kompetensiIds: [5, 6],
            jumlah: 3,
            tingkatKesulitan: 'sedang',
            versiKurikulum: '2013'
        );

        $result = $this->client->matchSoalToSiswa($permintaan);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['soalId']);
        $this->assertEquals('mudah', $result[0]['tingkatKesulitan']);
    }

    /** @test */
    public function validateHasil_dengan_valid_mengembalikan_true(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/validate-hasil' => Http::response([
                'valid' => true,
            ], 200),
        ]);

        $hasilData = [
            'nilai' => 72.0,
            'benar' => 18,
            'salah' => 2,
            'kosong' => 0,
        ];

        $result = $this->client->validateHasil(1, $hasilData);

        $this->assertTrue($result);
    }

    /** @test */
    public function validateHasil_dengan_invalid_mengembalikan_false(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/validate-hasil' => Http::response([
                'valid' => false,
            ], 200),
        ]);

        $hasilData = [
            'nilai' => 100.0, // mismatch dengan benar/salah
            'benar' => 18,
            'salah' => 2,
            'kosong' => 0,
        ];

        $result = $this->client->validateHasil(1, $hasilData);

        $this->assertFalse($result);
    }

    /** @test */
    public function validateHasil_ketika_service_tidak_tersedia_mengembalikan_true(): void
    {
        Http::fake([
            'http://localhost:8001/hitung/validate-hasil' => Http::response([], 503),
        ]);

        $hasilData = [
            'nilai' => 72.0,
            'benar' => 18,
            'salah' => 2,
            'kosong' => 0,
        ];

        // Should return true (fail open) and log warning
        $result = $this->client->validateHasil(1, $hasilData);

        $this->assertTrue($result);
    }

    /** @test */
    public function client_dengan_token_tidak_dikonfigurasi_melempar_error(): void
    {
        config(['services.perhitungan' => [
            'url' => 'http://localhost:8001',
            'token' => null,
        ]]);

        $this->expectException(PerhitunganKonfigurasiException::class);

        new PerhitunganClient();
    }

    /** @test */
    public function client_menggunakan_timeout_dari_config(): void
    {
        config([
            'services.perhitungan' => [
                'url' => 'http://localhost:8001',
                'token' => 'test-token',
                'timeout' => 10,
                'retry' => 2,
            ],
        ]);

        $client = new PerhitunganClient();

        // Verify timeout is set (via reflection since it's private)
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(10, $property->getValue($client));
    }

    /** @test */
    public function client_memiliki_backoff_schedule(): void
    {
        $backoff = $this->client->getRetryBackoff();

        $this->assertEquals([10, 30, 60, 120, 300], $backoff);
    }
}
