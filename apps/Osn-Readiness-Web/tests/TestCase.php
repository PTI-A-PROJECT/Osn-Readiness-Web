<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Container Docker mengekspor APP_ENV/DB_DATABASE, dan env proses itu
        // mengalahkan <env> di phpunit.xml. Tanpa override di bawah,
        // RefreshDatabase akan memakai database pengembangan.
        $this->paksaEnv('APP_ENV', 'testing');
        $this->paksaEnv('DB_CONNECTION', 'pgsql');
        $this->paksaEnv('DB_DATABASE', 'osn_readiness_testing');

        parent::setUp();

        // Pengaman terakhir: jangan pernah biarkan test berjalan di database
        // yang bukan database testing.
        $database = (string) config('database.connections.pgsql.database');

        if (! str_ends_with($database, '_testing')) {
            $this->fail("Test berhenti: database '{$database}' bukan database testing.");
        }
    }

    private function paksaEnv(string $nama, string $nilai): void
    {
        putenv("{$nama}={$nilai}");
        $_ENV[$nama] = $nilai;
        $_SERVER[$nama] = $nilai;
    }
}
