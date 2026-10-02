<?php

namespace Tests\Unit\Jobs;

use App\Contracts\Services\PenilaianServiceInterface;
use App\Exceptions\PerhitunganKonfigurasiException;
use App\Exceptions\PerhitunganTidakTersediaException;
use App\Jobs\NilaiUlangJob;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;

class NilaiUlangJobTest extends TestCase
{
    /** @test */
    public function job_dengan_jenis_pretest_memanggil_service(): void
    {
        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->with('pretest', 1);
        });

        $job = new NilaiUlangJob('pretest', 1);
        $job->handle($penilaianService);
    }

    /** @test */
    public function job_dengan_jenis_simulasi_memanggil_service(): void
    {
        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->with('simulasi', 5);
        });

        $job = new NilaiUlangJob('simulasi', 5);
        $job->handle($penilaianService);
    }

    /** @test */
    public function job_dengan_jenis_latihan_memanggil_service(): void
    {
        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->with('latihan', 10);
        });

        $job = new NilaiUlangJob('latihan', 10);
        $job->handle($penilaianService);
    }

    /** @test */
    public function job_dengan_jenis_invalid_fail(): void
    {
        Log::spy();

        $penilaianService = $this->mock(PenilaianServiceInterface::class);

        $job = new NilaiUlangJob('invalid_jenis', 1);
        $job->handle($penilaianService);

        Log::shouldHaveReceived('error');
    }

    /** @test */
    public function job_ketika_temporary_failure_release_kembali(): void
    {
        $exception = new PerhitunganTidakTersediaException('Service unavailable');

        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->andThrow($exception);
        });

        $job = new NilaiUlangJob('pretest', 1);

        // Mock release method
        $job = $this->partialMock(NilaiUlangJob::class, function (MockInterface $mock) {
            $mock->shouldReceive('release')->once();
        });

        $job->handle($penilaianService);

        $job->release();
    }

    /** @test */
    public function job_ketika_konfigurasi_error_fail_permanen(): void
    {
        Log::spy();

        $exception = new PerhitunganKonfigurasiException('Bad configuration');

        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->andThrow($exception);
        });

        $job = $this->partialMock(NilaiUlangJob::class, function (MockInterface $mock) {
            $mock->shouldReceive('fail')
                ->once()
                ->with(\Mockery::any(PerhitunganKonfigurasiException::class));
        });

        $job->handle($penilaianService);

        Log::shouldHaveReceived('error');
    }

    /** @test */
    public function job_memiliki_konfigurasi_retry_yang_benar(): void
    {
        $job = new NilaiUlangJob('pretest', 1);

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([10, 30, 60, 120, 300], $job->backoff);
        $this->assertEquals(60, $job->timeout);
    }

    /** @test */
    public function job_memiliki_unique_id(): void
    {
        $job = new NilaiUlangJob('simulasi', 42);

        $this->assertEquals('simulasi:42', $job->uniqueId());
    }

    /** @test */
    public function job_memiliki_unique_for_timeout(): void
    {
        $job = new NilaiUlangJob('pretest', 1);

        $this->assertEquals(3600, $job->uniqueFor()); // 1 hour
    }

    /** @test */
    public function job_idempoten_dengan_same_parameters(): void
    {
        $job1 = new NilaiUlangJob('pretest', 1);
        $job2 = new NilaiUlangJob('pretest', 1);

        $this->assertEquals($job1->uniqueId(), $job2->uniqueId());
    }

    /** @test */
    public function job_berbeda_untuk_jenis_berbeda(): void
    {
        $job1 = new NilaiUlangJob('pretest', 1);
        $job2 = new NilaiUlangJob('simulasi', 1);

        $this->assertNotEquals($job1->uniqueId(), $job2->uniqueId());
    }

    /** @test */
    public function job_berbeda_untuk_id_berbeda(): void
    {
        $job1 = new NilaiUlangJob('pretest', 1);
        $job2 = new NilaiUlangJob('pretest', 2);

        $this->assertNotEquals($job1->uniqueId(), $job2->uniqueId());
    }

    /** @test */
    public function job_log_info_pada_start(): void
    {
        Log::spy();

        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('selesaikanPenilaian')->once();
        });

        $job = new NilaiUlangJob('pretest', 1);
        $job->handle($penilaianService);

        Log::shouldHaveReceived('info')->with('NilaiUlangJob started', \Mockery::any());
    }

    /** @test */
    public function job_log_info_pada_completion(): void
    {
        Log::spy();

        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('selesaikanPenilaian')->once();
        });

        $job = new NilaiUlangJob('pretest', 1);
        $job->handle($penilaianService);

        Log::shouldHaveReceived('info')->with('NilaiUlangJob completed', \Mockery::any());
    }

    /** @test */
    public function job_log_warning_pada_temporary_failure(): void
    {
        Log::spy();

        $exception = new PerhitunganTidakTersediaException('Service down');

        $penilaianService = $this->mock(PenilaianServiceInterface::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('selesaikanPenilaian')
                ->once()
                ->andThrow($exception);
        });

        $job = $this->partialMock(NilaiUlangJob::class, function (MockInterface $mock) {
            $mock->shouldReceive('release')->once();
        });

        $job->handle($penilaianService);

        Log::shouldHaveReceived('warning')->with(
            'NilaiUlangJob temporary failure - will retry',
            \Mockery::any()
        );
    }

    /** @test */
    public function job_backoff_using_seconds(): void
    {
        $job = new NilaiUlangJob('pretest', 1);

        $backoff = $job->backoffUsingSeconds();

        $this->assertEquals([10, 30, 60, 120, 300], $backoff);
    }
}
