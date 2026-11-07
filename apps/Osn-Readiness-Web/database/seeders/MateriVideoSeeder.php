<?php

namespace Database\Seeders;

use App\Models\Materi;
use App\Models\MateriVideo;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

class MateriVideoSeeder extends Seeder
{
    /**
     * Seed supplied YouTube links by the existing material level and order.
     * Safe to rerun: existing material/video pairs are updated, not duplicated.
     * Materials are never created, renamed, or deleted.
     */
    public function run(): void
    {
        $data = [
            'Kabupaten/Kota' => [
                1 => [
                    ['id' => 'uFsrT1m3V2g', 'type' => 'video'],
                    ['id' => 'gXA6WL1xIKo', 'type' => 'video'],
                    ['id' => 'McGZ2JwRESk', 'type' => 'video'],
                    ['id' => 'wA1ZltkqypM', 'type' => 'video'],
                    ['id' => '8vXoj1SYDaU', 'type' => 'video'],
                ],
                2 => [
                    ['id' => '7OgJ6lJ8cMk', 'type' => 'video'],
                    ['id' => 'lcHdtwUl5_A', 'type' => 'video'],
                    ['id' => 'A1EVg6s0NDg', 'type' => 'video'],
                ],
                3 => [
                    ['id' => 'nRUqrvdWryc', 'type' => 'video'],
                    ['id' => 'pDjYF9siBwE', 'type' => 'video'],
                ],
                // Kabupaten/Kota urutan 4 is "Simulasi & Optimisasi".
                // The supplied Topik 4 is Logika Penalaran, so it is
                // intentionally not assigned to a different subject.
                5 => [
                    ['id' => 'PLZS-MHyEIRo4Ze0bbGB1WKBSNMPzi-eWI', 'type' => 'playlist'],
                ],
            ],
            'Provinsi' => [
                // Urutan 1 Dasar Pemrograman: supplied IDs were reported unavailable.
                2 => [
                    ['id' => 'Ojekeugeooa', 'type' => 'video'],
                ],
                3 => [
                    ['id' => 'Egjvn0aszvi', 'type' => 'video'],
                ],
                4 => [
                    ['id' => 'Gzmqns0vlis', 'type' => 'video'],
                ],
                5 => [
                    ['id' => 'Rzkdtp4mgi0', 'type' => 'video'],
                ],
                6 => [
                    ['id' => 'Clbwrkhozu8', 'type' => 'video'],
                    ['id' => 'Z9wq04ob0be', 'type' => 'video'],
                ],
                7 => [
                    ['id' => 'Qsnkhwgkcuk', 'type' => 'video'],
                    ['id' => 'W37fs6kdfuk', 'type' => 'video'],
                ],
                8 => [
                    ['id' => '8whuadli4rq', 'type' => 'video'],
                    ['id' => 'Dmdsfzdjjo8', 'type' => 'video'],
                    ['id' => 'E_Iplykmzh0', 'type' => 'video'],
                ],
                9 => [
                    ['id' => 'Zlezeckea6i', 'type' => 'video'],
                    ['id' => 'Jgw_Pxeptna', 'type' => 'video'],
                    ['id' => 'Xtcna3thbyi', 'type' => 'video'],
                ],
                10 => [
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

            foreach ($materials as $order => $videos) {
                $material = Materi::query()
                    ->where('tingkat_id', $level->id)
                    ->where('urutan', $order)
                    ->first();

                if (! $material) {
                    $skipped[] = "Materi tidak ditemukan: {$levelName}, urutan {$order}";
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
                        'judul' => $material->judul.' - '.($isPlaylist ? 'Playlist' : 'Video '.($index + 1)),
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
        $this->command?->warn('Belum dipetakan: Kabupaten/Kota Topik 4 (Logika Penalaran) karena materi database urutan 4 adalah Simulasi & Optimisasi.');
        $this->command?->warn('Provinsi Dasar Pemrograman dilewati karena tiga URL yang diberikan dilaporkan tidak tersedia.');
    }
}
