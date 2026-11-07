<?php

namespace Database\Seeders;

use App\Models\Materi;
use App\Models\MateriVideo;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

class MateriVideoSeeder extends Seeder
{
    /**
     * Seed YouTube links supplied for existing OSN materials.
     *
     * This seeder is safe to run repeatedly: each material/video pair is
     * updated rather than duplicated. It never creates or deletes materials.
     */
    public function run(): void
    {
        $data = [
            'Kabupaten/Kota' => [
                'Aljabar Boolean & Teori Himpunan' => [
                    ['id' => 'uFsrT1m3V2g', 'type' => 'video'],
                    ['id' => 'gXA6WL1xIKo', 'type' => 'video'],
                    ['id' => 'McGZ2JwRESk', 'type' => 'video'],
                    ['id' => 'wA1ZltkqypM', 'type' => 'video'],
                    ['id' => '8vXoj1SYDaU', 'type' => 'video'],
                ],
                'Teori Graf & Geometri Analitik' => [
                    ['id' => '7OgJ6lJ8cMk', 'type' => 'video'],
                    ['id' => 'lcHdtwUl5_A', 'type' => 'video'],
                    ['id' => 'A1EVg6s0NDg', 'type' => 'video'],
                ],
                'Kombinatorika Lanjut, Prinsip Sarang Merpati, dan Rekurensi' => [
                    ['id' => 'nRUqrvdWryc', 'type' => 'video'],
                    ['id' => 'pDjYF9siBwE', 'type' => 'video'],
                ],
                'Logika Penalaran dan Penarikan Kesimpulan' => [
                    ['id' => 'IW5U-SBUnwo', 'type' => 'video'],
                    ['id' => '0oc3gL0V-N4', 'type' => 'video'],
                ],
                'Fondasi Algoritma & Pemrograman' => [
                    ['id' => 'PLZS-MHyEIRo4Ze0bbGB1WKBSNMPzi-eWI', 'type' => 'playlist'],
                ],
            ],
            'Provinsi' => [
                // Tiga URL awal Dasar Pemrograman dilaporkan tidak tersedia.
                // Tidak diganti otomatis dengan kandidat yang belum diverifikasi.
                'Operasi Logika' => [
                    ['id' => 'Ojekeugeooa', 'type' => 'video'],
                ],
                'Aritmetika' => [
                    ['id' => 'Egjvn0aszvi', 'type' => 'video'],
                ],
                'Aturan Berhitung' => [
                    ['id' => 'Gzmqns0vlis', 'type' => 'video'],
                ],
                'Rekursif' => [
                    ['id' => 'Rzkdtp4mgi0', 'type' => 'video'],
                ],
                'Pencarian dan Pengurutan' => [
                    ['id' => 'Clbwrkhozu8', 'type' => 'video'],
                    ['id' => 'Z9wq04ob0be', 'type' => 'video'],
                ],
                'Strategi Pemecahan Masalah' => [
                    ['id' => 'Qsnkhwgkcuk', 'type' => 'video'],
                    ['id' => 'W37fs6kdfuk', 'type' => 'video'],
                ],
                'Struktur Data' => [
                    ['id' => '8whuadli4rq', 'type' => 'video'],
                    ['id' => 'Dmdsfzdjjo8', 'type' => 'video'],
                    ['id' => 'E_Iplykmzh0', 'type' => 'video'],
                ],
                'Graf dan Tree' => [
                    ['id' => 'Zlezeckea6i', 'type' => 'video'],
                    ['id' => 'Jgw_Pxeptna', 'type' => 'video'],
                    ['id' => 'Xtcna3thbyi', 'type' => 'video'],
                ],
                'Geometri Dasar' => [
                    ['id' => 'A1evg6s0ndg', 'type' => 'video'],
                    ['id' => '5jkgct-Jytw', 'type' => 'video'],
                ],
            ],
        ];

        $inserted = 0;
        $updated = 0;
        $skipped = [];

        foreach ($data as $levelName => $materials) {
            $level = TingkatSeleksi::query()
                ->where('nama_tingkat', $levelName)
                ->first();

            if (! $level) {
                $skipped[] = "Tingkat tidak ditemukan: {$levelName}";
                continue;
            }

            foreach ($materials as $title => $videos) {
                $material = Materi::query()
                    ->where('tingkat_id', $level->id)
                    ->where('judul', $title)
                    ->first();

                if (! $material) {
                    $skipped[] = "Materi tidak ditemukan: {$levelName} / {$title}";
                    continue;
                }

                foreach ($videos as $index => $videoData) {
                    $isPlaylist = $videoData['type'] === 'playlist';
                    $url = $isPlaylist
                        ? 'https://www.youtube.com/playlist?list='.$videoData['id']
                        : 'https://www.youtube.com/watch?v='.$videoData['id'];

                    $video = MateriVideo::query()->firstOrNew([
                        'materi_id' => $material->id,
                        'youtube_id' => $videoData['id'],
                    ]);

                    $wasExisting = $video->exists;
                    $video->fill([
                        'judul' => $title.' - '.($isPlaylist ? 'Playlist' : 'Video '.($index + 1)),
                        'youtube_url' => $url,
                        'tipe' => $videoData['type'],
                        'urutan' => $index + 1,
                    ]);
                    $video->save();

                    $wasExisting ? $updated++ : $inserted++;
                }
            }
        }

        $this->command?->info("Playlist video selesai: {$inserted} ditambahkan, {$updated} diperbarui.");
        foreach ($skipped as $message) {
            $this->command?->warn($message);
        }
        $this->command?->warn('Dasar Pemrograman Provinsi dilewati karena tiga URL awal dilaporkan tidak tersedia dan link pengganti belum diverifikasi.');
    }
}
