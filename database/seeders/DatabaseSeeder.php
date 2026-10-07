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

        // Bank soal kabupaten hanya untuk pengembangan lokal dan test.
        // Tanpa KONTEN_KABUPATEN_FOLDER, seeder hanya mencetak peringatan.
        if (app()->environment('local', 'testing')) {
            $this->call(KontenKabupatenSeeder::class);
        }
    }
}
