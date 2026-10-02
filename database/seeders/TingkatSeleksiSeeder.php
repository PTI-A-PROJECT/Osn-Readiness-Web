<?php

namespace Database\Seeders;

use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

class TingkatSeleksiSeeder extends Seeder
{
    public function run(): void
    {
        TingkatSeleksi::firstOrCreate(
            ['nama' => 'Kabupaten'],
            ['deskripsi' => 'Tingkat Seleksi Kabupaten', 'urutan' => 1]
        );

        TingkatSeleksi::firstOrCreate(
            ['nama' => 'Provinsi'],
            ['deskripsi' => 'Tingkat Seleksi Provinsi', 'urutan' => 2]
        );
    }
}
