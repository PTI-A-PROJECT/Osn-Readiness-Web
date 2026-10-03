<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TingkatSeleksiSeeder::class,
            AturanPemetaanSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Konten contoh hanya untuk pengembangan lokal dan test.
        if (app()->environment('local', 'testing')) {
            $this->call(KontenContohSeeder::class);
        }
    }
}
