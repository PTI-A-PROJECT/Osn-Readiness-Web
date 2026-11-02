<?php

namespace App\Services;

use App\Contracts\Repositories\MateriRepositoryInterface;
use App\Contracts\Repositories\ProgressBelajarRepositoryInterface;
use App\Contracts\Repositories\QuizPengerjaanRepositoryInterface;
use App\Contracts\Repositories\RekomendasiMateriRepositoryInterface;
use App\Contracts\Repositories\TingkatSeleksiRepositoryInterface;
use App\Contracts\Services\BelajarServiceInterface;
use App\Contracts\Services\PutaranServiceInterface;
use App\DTOs\StatusPutaran;
use App\Enums\StatusProgress;
use App\Exceptions\BelumPretestException;
use App\Exceptions\TingkatTerkunciException;
use App\Models\Materi;
use App\Models\ProgressBelajar;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BelajarService implements BelajarServiceInterface
{
    public function __construct(
        private readonly MateriRepositoryInterface $materiRepository,
        private readonly ProgressBelajarRepositoryInterface $progressRepository,
        private readonly RekomendasiMateriRepositoryInterface $rekomendasiRepository,
        private readonly QuizPengerjaanRepositoryInterface $quizPengerjaanRepository,
        private readonly TingkatSeleksiRepositoryInterface $tingkatRepository,
        private readonly PutaranServiceInterface $putaranService,
    ) {}

    public function daftar(User $user, int $tingkatId): Collection
    {
        $status = $this->pastikanTerbuka($user, $tingkatId);

        $materi = $this->materiRepository->untukTingkat($tingkatId);

        if ($materi->isEmpty()) {
            return new Collection;
        }

        $tanda = $this->tandaMateriWajib($status);
        $progress = $this->progressRepository->untukUserDiTingkat($user, $tingkatId);
        $nilaiTerbaik = $this->nilaiLatihanTerbaik($user, $materi);

        $baris = $materi->map(fn (Materi $satu): array => $this->butir(
            materi: $satu,
            wajib: $tanda['wajib'][(int) $satu->id] ?? false,
            prioritas: $tanda['prioritas'][(int) $satu->id] ?? null,
            progress: $progress->get((int) $satu->id),
            nilaiTerbaik: $nilaiTerbaik[(int) $satu->id] ?? null,
        ));

        // Materi wajib ditampilkan lebih dulu, diurutkan prioritas lalu
        // urutan materi.
        return $baris->sort(function (array $a, array $b): int {
            if ($a['wajib'] !== $b['wajib']) {
                return $a['wajib'] ? -1 : 1;
            }

            if ($a['wajib']) {
                return ($a['prioritas'] ?? PHP_INT_MAX) <=> ($b['prioritas'] ?? PHP_INT_MAX);
            }

            return $a['urutan'] <=> $b['urutan'];
        })->values();
    }

    public function detail(User $user, Materi $materi): array
    {
        $status = $this->pastikanTerbuka($user, (int) $materi->tingkat_id);

        $tanda = $this->tandaMateriWajib($status);
        $progress = $this->progressRepository->find($user, (int) $materi->id);
        $nilaiTerbaik = $this->nilaiLatihanTerbaik($user, new Collection([$materi]));

        return $this->butir(
            materi: $materi,
            wajib: $tanda['wajib'][(int) $materi->id] ?? false,
            prioritas: $tanda['prioritas'][(int) $materi->id] ?? null,
            progress: $progress,
            nilaiTerbaik: $nilaiTerbaik[(int) $materi->id] ?? null,
            isi: true,
        );
    }

    public function perbaruiProgress(User $user, int $materiId, string $status): ProgressBelajar
    {
        $materi = $this->materiRepository->findOrFail($materiId);
        $tingkat = $materi->tingkat;

        $statusPutaran = $this->putaranService->status($user, $tingkat);

        if (! $statusPutaran->tingkatTerbuka) {
            throw new TingkatTerkunciException("Tingkat {$tingkat->nama_tingkat} belum terbuka.");
        }

        // Menandai selesai hanya dicatat saat ada putaran aktif; tanpa itu
        // siswa bisa memenuhi syarat simulasi sebelum pre-test baru dipetakan.
        if ($statusPutaran->putaranAktifId === null) {
            throw new BelumPretestException('Tandai materi selesai setelah pre-test.');
        }

        return $this->progressRepository->simpan($user, $materiId, [
            'status' => $status,
            'persentase' => $status === StatusProgress::Selesai->value ? 100 : 0,
            'tanggal_selesai' => $status === StatusProgress::Selesai->value ? now() : null,
        ]);
    }

    /**
     * Tandai satu submateri selesai. Progress materi dihitung dari jumlah
     * submateri unik yang selesai dan disimpan pada akun siswa.
     *
     * @return array<string, mixed>
     */
    public function tandaiSubmateriSelesai(User $user, int $materiId, string $submateriId): array
    {
        $materi = $this->materiRepository->findOrFail($materiId);
        $statusPutaran = $this->pastikanTerbuka($user, (int) $materi->tingkat_id);

        if ($statusPutaran->putaranAktifId === null) {
            throw new BelumPretestException('Tandai materi selesai setelah pre-test.');
        }

        $bagian = $this->pecahSubmateri($materi->isi_materi, $materi->judul);
        $target = null;

        foreach ($bagian as $item) {
            if ($item['id'] === $submateriId) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            abort(404, 'Submateri tidak ditemukan.');
        }

        return DB::transaction(function () use ($user, $materi, $materiId, $submateriId, $target, $bagian): array {
            $progress = $this->progressRepository->find($user, $materiId);

            if ($progress === null) {
                $progress = $this->progressRepository->simpan($user, $materiId, [
                    'status' => StatusProgress::Belajar->value,
                    'persentase' => 0,
                    'tanggal_selesai' => null,
                ]);
            }

            $totalSubmateri = count($bagian);
            $kodeSubmateri = array_column($bagian, 'id');

            // Progress lama yang sudah 100% tetap dianggap selesai.
            if ($progress->status === StatusProgress::Selesai) {
                $jumlahSelesai = $totalSubmateri;
            } else {
                $sekarang = now();
                DB::table('progress_submateri')->updateOrInsert(
                    [
                        'progress_belajar_id' => $progress->id,
                        'kode' => $submateriId,
                    ],
                    [
                        'judul' => $target['judul'],
                        'selesai_pada' => $sekarang,
                        'created_at' => $sekarang,
                        'updated_at' => $sekarang,
                    ],
                );

                $jumlahSelesai = DB::table('progress_submateri')
                    ->where('progress_belajar_id', $progress->id)
                    ->whereIn('kode', $kodeSubmateri)
                    ->count();

                $semuaSelesai = $jumlahSelesai >= $totalSubmateri;
                $progress = $this->progressRepository->simpan($user, $materiId, [
                    'status' => $semuaSelesai
                        ? StatusProgress::Selesai->value
                        : StatusProgress::Belajar->value,
                    'persentase' => (int) round(($jumlahSelesai / $totalSubmateri) * 100),
                    'tanggal_selesai' => $semuaSelesai ? now() : null,
                ]);
            }

            return [
                'materi_id' => (int) $materi->id,
                'submateri_id' => $submateriId,
                'status' => $progress->status->value,
                'persentase' => (int) $progress->persentase,
                'tanggal_selesai' => $progress->tanggal_selesai?->toISOString(),
                'submateri_selesai' => $jumlahSelesai,
                'total_submateri' => $totalSubmateri,
                'submateri' => $this->daftarSubmateri($materi, $progress),
            ];
        });
    }

    private function pastikanTerbuka(User $user, int $tingkatId): StatusPutaran
    {
        $tingkat = $this->tingkatRepository->find($tingkatId);

        if (! $tingkat instanceof TingkatSeleksi) {
            throw (new ModelNotFoundException)->setModel(TingkatSeleksi::class, [$tingkatId]);
        }

        $status = $this->putaranService->status($user, $tingkat);

        if (! $status->tingkatTerbuka) {
            throw new TingkatTerkunciException("Tingkat {$tingkat->nama_tingkat} belum terbuka.");
        }

        return $status;
    }

    /**
     * Tanda wajib dan prioritas diambil dari rekomendasi_materi putaran
     * aktif; tanpa putaran aktif tidak ada materi yang wajib.
     *
     * @return array{wajib: array<int, bool>, prioritas: array<int, int>}
     */
    private function tandaMateriWajib(StatusPutaran $statusPutaran): array
    {
        if ($statusPutaran->putaranAktifId === null) {
            return ['wajib' => [], 'prioritas' => []];
        }

        $rekomendasi = $this->rekomendasiRepository->untukPretest($statusPutaran->putaranAktifId);

        $wajib = [];
        $prioritas = [];

        foreach ($rekomendasi as $baris) {
            $wajib[(int) $baris->materi_id] = true;
            $prioritas[(int) $baris->materi_id] = (int) $baris->prioritas;
        }

        return ['wajib' => $wajib, 'prioritas' => $prioritas];
    }

    /**
     * Nilai latihan sebuah materi adalah nilai tertinggi dari semua
     * pengerjaan quiz materi itu yang sudah selesai.
     *
     * @param  Collection<int, Materi>  $materi
     * @return array<int, float>
     */
    private function nilaiLatihanTerbaik(User $user, Collection $materi): array
    {
        // Latihan dan nilai terbaiknya diambil sekaligus, bukan per materi.
        (new EloquentCollection($materi->all()))->loadMissing('quiz');

        $nilaiPerQuiz = $this->quizPengerjaanRepository->nilaiTerbaikPerQuiz(
            $user,
            $materi->map(fn (Materi $satu): ?int => $satu->quiz?->id)->filter()->values()->all(),
        );

        $nilai = [];

        foreach ($materi as $satu) {
            $quizId = $satu->quiz?->id;

            if ($quizId !== null && isset($nilaiPerQuiz[(int) $quizId])) {
                $nilai[(int) $satu->id] = $nilaiPerQuiz[(int) $quizId];
            }
        }

        return $nilai;
    }

    /**
     * Pisahkan konten Markdown berdasarkan heading level dua. Jika materi
     * belum memiliki heading, seluruh isi tetap menjadi satu submateri.
     *
     * @return list<array{id: string, judul: string, konten: string}>
     */
    private function pecahSubmateri(?string $isi, string $judulMateri): array
    {
        $isi = (string) $isi;

        if (trim($isi) === '') {
            return [[
                'id' => 'materi-utama',
                'judul' => $judulMateri !== '' ? $judulMateri : 'Materi Utama',
                'konten' => '',
            ]];
        }

        $baris = preg_split('/\R/u', $isi) ?: [];
        $hasil = [];
        $judulBagian = null;
        $kontenBagian = [];
        $dalamKode = false;
        $penandaKode = null;
        $slugDipakai = [];

        $simpanBagian = function (string $judul, array $konten) use (&$hasil, &$slugDipakai): void {
            $slug = Str::slug($judul);
            $slug = $slug !== '' ? substr($slug, 0, 90) : 'submateri';
            $slugDipakai[$slug] = ($slugDipakai[$slug] ?? 0) + 1;
            $id = $slugDipakai[$slug] === 1 ? $slug : $slug.'-'.$slugDipakai[$slug];

            $hasil[] = [
                'id' => $id,
                'judul' => trim($judul),
                'konten' => trim(implode("\n", $konten)),
            ];
        };

        foreach ($baris as $teksBaris) {
            $trimmed = ltrim($teksBaris);
            $penanda = str_starts_with($trimmed, '~~~')
                ? '~'
                : (str_starts_with($trimmed, str_repeat(chr(96), 3)) ? chr(96) : null);

            if ($penanda !== null) {
                if (! $dalamKode) {
                    $dalamKode = true;
                    $penandaKode = $penanda;
                } elseif ($penanda === $penandaKode) {
                    $dalamKode = false;
                    $penandaKode = null;
                }
            }

            if (! $dalamKode && preg_match('/^\s{0,3}##[ \t]+(.+?)[ \t]*#*[ \t]*$/u', $teksBaris, $cocok) === 1) {
                if ($judulBagian !== null) {
                    $simpanBagian($judulBagian, $kontenBagian);
                } elseif (trim(implode("\n", $kontenBagian)) !== '') {
                    $simpanBagian('Pendahuluan', $kontenBagian);
                }

                $judulBagian = trim($cocok[1]);
                $kontenBagian = [];

                continue;
            }

            $kontenBagian[] = $teksBaris;
        }

        if ($judulBagian !== null) {
            $simpanBagian($judulBagian, $kontenBagian);
        }

        if ($hasil === []) {
            return [[
                'id' => 'materi-utama',
                'judul' => $judulMateri !== '' ? $judulMateri : 'Materi Utama',
                'konten' => trim($isi),
            ]];
        }

        return $hasil;
    }

    /**
     * @return list<array{id: string, judul: string, konten: string, selesai: bool, tanggal_selesai: string|null}>
     */
    private function daftarSubmateri(Materi $materi, ?ProgressBelajar $progress): array
    {
        $bagian = $this->pecahSubmateri($materi->isi_materi, $materi->judul);
        $kode = array_column($bagian, 'id');
        $tersimpan = $progress === null
            ? collect()
            : DB::table('progress_submateri')
                ->where('progress_belajar_id', $progress->id)
                ->whereIn('kode', $kode)
                ->get()
                ->keyBy('kode');
        $legacySelesai = $progress?->status === StatusProgress::Selesai;

        return array_map(function (array $item) use ($tersimpan, $legacySelesai, $progress): array {
            $rekaman = $tersimpan->get($item['id']);
            $selesai = $legacySelesai || $rekaman !== null;

            return [
                'id' => $item['id'],
                'judul' => $item['judul'],
                'konten' => $item['konten'],
                'selesai' => $selesai,
                'tanggal_selesai' => $legacySelesai
                    ? $progress?->tanggal_selesai?->toISOString()
                    : $rekaman?->selesai_pada,
            ];
        }, $bagian);
    }

    /**
     * @return array<string, mixed>
     */
    private function butir(
        Materi $materi,
        bool $wajib,
        ?int $prioritas,
        ?ProgressBelajar $progress,
        ?float $nilaiTerbaik,
        bool $isi = false,
    ): array {
        $baris = [
            'id' => (int) $materi->id,
            'tingkat_id' => (int) $materi->tingkat_id,
            'kompetensi_id' => (int) $materi->kompetensi_id,
            'urutan' => (int) $materi->urutan,
            'judul' => $materi->judul,
            'deskripsi' => $materi->deskripsi,
            'wajib' => $wajib,
            'prioritas' => $prioritas,
            'progress' => $progress === null ? null : [
                'status' => $progress->status->value,
                'persentase' => (int) $progress->persentase,
                'tanggal_selesai' => $progress->tanggal_selesai?->toISOString(),
            ],
            'nilai_latihan_terbaik' => $nilaiTerbaik,
            'quiz_id' => $materi->quiz === null ? null : (int) $materi->quiz->id,
            'latihan_belum_tersedia' => $materi->quiz === null,
        ];

        if ($isi) {
            $baris['isi_materi'] = $materi->isi_materi;
            $baris['file_materi'] = $materi->file_materi;
            $baris['gambar'] = $materi->gambar;
            $baris['submateri'] = $this->daftarSubmateri($materi, $progress);
        }

        return $baris;
    }
}
