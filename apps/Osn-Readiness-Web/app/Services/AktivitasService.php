<?php

namespace App\Services;

use App\Contracts\Clients\PerhitunganClientInterface;
use App\Contracts\Services\AktivitasServiceInterface;
use App\Models\LogAktivitas;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AktivitasService implements AktivitasServiceInterface
{
    public function __construct(
        private readonly ?PerhitunganClientInterface $perhitungan = null,
    ) {}
    /** Log disimpan 90 hari, lalu dibersihkan oleh `aktivitas:prune`. */
    public const RETENSI_HARI = 90;

    private const LABEL = [
        'akun.daftar' => 'Mendaftar akun',
        'akun.masuk' => 'Masuk',
        'akun.masuk_google' => 'Masuk dengan Google',
        'akun.keluar' => 'Keluar',
        'akun.profil_diubah' => 'Mengubah profil',
        'akun.referal_diisi' => 'Mengisi kode referal',
        'akun.referal_diubah' => 'Mengubah kode referal',
        'akun.dinonaktifkan' => 'Akun dinonaktifkan',
        'akun.dihapus' => 'Akun dihapus',
        'pretest.mulai' => 'Mengerjakan pretest',
        'pretest.selesai' => 'Menyelesaikan pretest',
        'materi.selesai' => 'Menyelesaikan materi',
        'submateri.selesai' => 'Menyelesaikan submateri',
        'latihan.mulai' => 'Mengerjakan latihan',
        'latihan.selesai' => 'Menyelesaikan latihan',
        'simulasi.mulai' => 'Mengerjakan simulasi',
        'simulasi.selesai' => 'Menyelesaikan simulasi',
        'tingkat.naik' => 'Kenaikan tingkat',
        'konten.diimpor' => 'Mengimpor konten',
    ];

    private const VERBA = [
        'dibuat' => 'Membuat',
        'diubah' => 'Mengubah',
        'dihapus' => 'Menghapus',
        'diunggah' => 'Mengunggah',
    ];

    private const ENTITAS = [
        'materi_video' => 'video materi',
        'konteks_soal' => 'konteks soal',
        'aturan_pemetaan' => 'aturan pemetaan',
    ];

    public function catat(
        ?User $user,
        string $aksi,
        string $kategori = 'akun',
        array $meta = [],
        ?string $subjekTipe = null,
        ?int $subjekId = null,
    ): void {
        $sekarang = now();

        // Debounce: aksi yang sama untuk subjek yang sama dalam 60 detik
        // dianggap satu kejadian (mis. ganti nama lalu foto dalam satu menit).
        $terakhir = LogAktivitas::query()
            ->where('user_id', $user?->id)
            ->where('aksi', $aksi)
            ->when($subjekTipe !== null, fn (Builder $q) => $q->where('subjek_tipe', $subjekTipe))
            ->when($subjekId !== null, fn (Builder $q) => $q->where('subjek_id', $subjekId))
            ->where('terjadi_pada', '>=', $sekarang->copy()->subSeconds(60))
            ->latest('terjadi_pada')
            ->first();

        if ($terakhir !== null) {
            $metaLama = $terakhir->meta ?? [];
            $metaLama['jumlah'] = ((int) ($metaLama['jumlah'] ?? 1)) + 1;
            $metaLama['field'] = array_values(array_unique(array_merge(
                (array) ($metaLama['field'] ?? []),
                (array) ($meta['field'] ?? []),
            )));

            $terakhir->forceFill(['meta' => $metaLama, 'terjadi_pada' => $sekarang])->save();

            return;
        }

        $request = app()->runningInConsole() ? null : request();
        $batch = $request?->attributes->get('aktivitas_batch');

        if ($batch === null) {
            $batch = (string) Str::uuid();
            $request?->attributes->set('aktivitas_batch', $batch);
        }

        LogAktivitas::create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'kategori' => $kategori,
            'subjek_tipe' => $subjekTipe,
            'subjek_id' => $subjekId,
            'meta' => $meta === [] ? null : $meta,
            'batch_id' => $batch,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() !== null
                ? Str::limit((string) $request->userAgent(), 250, '')
                : null,
            'terjadi_pada' => $sekarang,
        ]);
    }

    public function timeline(?int $userId, ?DateTimeInterface $dari = null, ?DateTimeInterface $sampai = null, ?string $kategori = null): array
    {
        $dari = $dari !== null
            ? Carbon::parse($dari)->startOfDay()
            : now()->subDays(self::RETENSI_HARI)->startOfDay();
        $sampai = $sampai !== null
            ? Carbon::parse($sampai)->endOfDay()
            : now()->endOfDay();

        $entri = [
            ...$this->entriLog($userId, $dari, $sampai),
            ...$this->entriPretest($userId, $dari, $sampai),
            ...$this->entriLatihan($userId, $dari, $sampai),
            ...$this->entriSimulasi($userId, $dari, $sampai),
            ...$this->entriMateri($userId, $dari, $sampai),
            ...$this->entriSubmateri($userId, $dari, $sampai),
            ...$this->entriKenaikanTingkat($userId, $dari, $sampai),
        ];

        if ($kategori !== null) {
            $entri = array_values(array_filter(
                $entri,
                fn (array $e): bool => $e['kategori'] === $kategori,
            ));
        }

        usort($entri, fn (array $a, array $b): int => strcmp($b['pada'], $a['pada']));

        $perTanggal = [];

        foreach ($entri as $e) {
            $perTanggal[substr($e['pada'], 0, 10)][] = $e;
        }

        $hasil = [];

        foreach ($perTanggal as $tanggal => $daftar) {
            $hasil[] = ['tanggal' => $tanggal, 'entri' => $daftar];
        }

        return $hasil;
    }

    public function detail(?int $userId, string $aksi, string $tanggal): array
    {
        return LogAktivitas::query()
            ->with('user:id,name,email')
            ->when($userId !== null, fn (Builder $q) => $q->where('user_id', $userId))
            ->where('aksi', $aksi)
            ->whereDate('terjadi_pada', $tanggal)
            ->orderByDesc('terjadi_pada')
            ->get()
            ->map(fn (LogAktivitas $log): array => [
                'user_id' => $log->user_id,
                'user_nama' => $log->user?->name,
                'user_email' => $log->user?->email,
                'pada' => $log->terjadi_pada?->toIso8601String(),
                'meta' => $log->meta,
                'ip' => $log->ip,
            ])
            ->all();
    }

    public function analisis(?int $tingkatId = null, ?string $peruntukan = null, ?int $userId = null, ?int $materiId = null): array
    {
        $bankSoal = $this->kumpulkanBankSoalAnalisis($tingkatId, $peruntukan, $materiId);
        $sesi = $this->kumpulkanSesiAnalisis($tingkatId, $peruntukan, $userId, $materiId);
        $pengerjaan = $this->kumpulkanPengerjaanAttempt($tingkatId, $peruntukan, $userId, $materiId);

        $klien = $this->perhitungan ?? app(PerhitunganClientInterface::class);

        return $klien->hitungAnalisisKlasik([
            'sesi' => $sesi,
            'bank_soal' => $bankSoal,
            'pengerjaan' => $pengerjaan,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kumpulkanBankSoalAnalisis(?int $tingkatId, ?string $peruntukan, ?int $materiId): array
    {
        $baris = DB::table('soal as s')
            ->join('materi as m', 'm.id', '=', 's.materi_id')
            ->whereNull('s.deleted_at')
            ->when($tingkatId !== null, fn ($q) => $q->where('s.tingkat_id', $tingkatId))
            ->when($peruntukan !== null, fn ($q) => $q->where('s.peruntukan', $peruntukan))
            ->when($materiId !== null, fn ($q) => $q->where('s.materi_id', $materiId))
            ->select(
                's.id',
                's.id_sumber',
                's.pertanyaan',
                's.materi_id',
                'm.judul as materi_judul',
                's.level',
                's.peruntukan',
            )
            ->selectRaw(
                '((SELECT COUNT(*) FROM pretest_jawaban pj WHERE pj.soal_id = s.id) + '
                .'(SELECT COUNT(*) FROM quiz_jawaban qj WHERE qj.soal_id = s.id) + '
                .'(SELECT COUNT(*) FROM hasil_simulasi_jawaban hj WHERE hj.soal_id = s.id)) as jumlah_keluar'
            )
            ->orderBy('s.id')
            ->get();

        return $baris
            ->map(fn ($row): array => [
                'soal_id' => (int) $row->id,
                'kode_soal' => $row->id_sumber ?: ('SOAL-'.str_pad((string) $row->id, 3, '0', STR_PAD_LEFT)),
                'pertanyaan_ringkas' => Str::limit(trim(strip_tags((string) $row->pertanyaan)), 110),
                'materi_id' => (int) $row->materi_id,
                'materi_judul' => (string) $row->materi_judul,
                'level' => (string) $row->level,
                'peruntukan' => (string) $row->peruntukan,
                'jumlah_keluar' => (int) $row->jumlah_keluar,
            ])
            ->all();
    }

    /**
     * @return list<array{sesi_id: int, user_id: int, nilai_total: float, jawaban: list<array{soal_id: int, status_benar: bool}>}>
     */
    private function kumpulkanSesiAnalisis(?int $tingkatId, ?string $peruntukan, ?int $userId, ?int $materiId): array
    {
        $sesi = [];

        // 1. Pretest
        if ($peruntukan === null || $peruntukan === 'pretest') {
            $pretestRows = DB::table('pretest as p')
                ->whereNotNull('p.nilai')
                ->when($tingkatId !== null, fn ($q) => $q->where('p.tingkat_id', $tingkatId))
                ->when($userId !== null, fn ($q) => $q->where('p.user_id', $userId))
                ->select('p.id', 'p.user_id', 'p.nilai')
                ->get();

            if ($pretestRows->isNotEmpty()) {
                $ids = $pretestRows->pluck('id')->all();
                $jawabanRows = DB::table('pretest_jawaban as pj')
                    ->join('soal as s', 's.id', '=', 'pj.soal_id')
                    ->whereIn('pj.pretest_id', $ids)
                    ->whereNotNull('pj.status_benar')
                    ->when($materiId !== null, fn ($q) => $q->where('s.materi_id', $materiId))
                    ->select('pj.pretest_id', 'pj.soal_id', 'pj.status_benar')
                    ->get()
                    ->groupBy('pretest_id');

                foreach ($pretestRows as $p) {
                    $jList = ($jawabanRows->get($p->id) ?? collect())
                        ->map(fn ($j): array => [
                            'soal_id' => (int) $j->soal_id,
                            'status_benar' => (bool) $j->status_benar,
                        ])
                        ->values()
                        ->all();

                    if ($jList !== []) {
                        $sesi[] = [
                            'sesi_id' => 1000000 + (int) $p->id,
                            'user_id' => (int) $p->user_id,
                            'nilai_total' => (float) $p->nilai,
                            'jawaban' => $jList,
                        ];
                    }
                }
            }
        }

        // 2. Latihan (Quiz)
        if ($peruntukan === null || $peruntukan === 'latihan') {
            $latihanRows = DB::table('quiz_pengerjaan as qp')
                ->join('quiz as q', 'q.id', '=', 'qp.quiz_id')
                ->join('materi as m', 'm.id', '=', 'q.materi_id')
                ->whereNotNull('qp.nilai')
                ->when($tingkatId !== null, fn ($query) => $query->where('m.tingkat_id', $tingkatId))
                ->when($materiId !== null, fn ($query) => $query->where('q.materi_id', $materiId))
                ->when($userId !== null, fn ($query) => $query->where('qp.user_id', $userId))
                ->select('qp.id', 'qp.user_id', 'qp.nilai')
                ->get();

            if ($latihanRows->isNotEmpty()) {
                $ids = $latihanRows->pluck('id')->all();
                $jawabanRows = DB::table('quiz_jawaban as qj')
                    ->join('soal as s', 's.id', '=', 'qj.soal_id')
                    ->whereIn('qj.pengerjaan_id', $ids)
                    ->whereNotNull('qj.status_benar')
                    ->when($materiId !== null, fn ($q) => $q->where('s.materi_id', $materiId))
                    ->select('qj.pengerjaan_id', 'qj.soal_id', 'qj.status_benar')
                    ->get()
                    ->groupBy('pengerjaan_id');

                foreach ($latihanRows as $qp) {
                    $jList = ($jawabanRows->get($qp->id) ?? collect())
                        ->map(fn ($j): array => [
                            'soal_id' => (int) $j->soal_id,
                            'status_benar' => (bool) $j->status_benar,
                        ])
                        ->values()
                        ->all();

                    if ($jList !== []) {
                        $sesi[] = [
                            'sesi_id' => 2000000 + (int) $qp->id,
                            'user_id' => (int) $qp->user_id,
                            'nilai_total' => (float) $qp->nilai,
                            'jawaban' => $jList,
                        ];
                    }
                }
            }
        }

        // 3. Simulasi
        if ($peruntukan === null || $peruntukan === 'simulasi') {
            $simulasiRows = DB::table('hasil_simulasi as hs')
                ->join('simulasi as sim', 'sim.id', '=', 'hs.simulasi_id')
                ->whereNotNull('hs.nilai')
                ->when($tingkatId !== null, fn ($q) => $q->where('sim.tingkat_id', $tingkatId))
                ->when($userId !== null, fn ($q) => $q->where('hs.user_id', $userId))
                ->select('hs.id', 'hs.user_id', 'hs.nilai')
                ->get();

            if ($simulasiRows->isNotEmpty()) {
                $ids = $simulasiRows->pluck('id')->all();
                $jawabanRows = DB::table('hasil_simulasi_jawaban as hj')
                    ->join('soal as s', 's.id', '=', 'hj.soal_id')
                    ->whereIn('hj.hasil_simulasi_id', $ids)
                    ->whereNotNull('hj.status_benar')
                    ->when($materiId !== null, fn ($q) => $q->where('s.materi_id', $materiId))
                    ->select('hj.hasil_simulasi_id', 'hj.soal_id', 'hj.status_benar')
                    ->get()
                    ->groupBy('hasil_simulasi_id');

                foreach ($simulasiRows as $hs) {
                    $jList = ($jawabanRows->get($hs->id) ?? collect())
                        ->map(fn ($j): array => [
                            'soal_id' => (int) $j->soal_id,
                            'status_benar' => (bool) $j->status_benar,
                        ])
                        ->values()
                        ->all();

                    if ($jList !== []) {
                        $sesi[] = [
                            'sesi_id' => 3000000 + (int) $hs->id,
                            'user_id' => (int) $hs->user_id,
                            'nilai_total' => (float) $hs->nilai,
                            'jawaban' => $jList,
                        ];
                    }
                }
            }
        }

        return $sesi;
    }

    /**
     * @return list<array{user_id: int, user_nama: string, jenis: string, target_id: int, target_nama: string, nilai: float, waktu: string}>
     */
    private function kumpulkanPengerjaanAttempt(?int $tingkatId, ?string $peruntukan, ?int $userId, ?int $materiId): array
    {
        $hasil = [];

        if (($peruntukan === null || $peruntukan === 'pretest') && $materiId === null) {
            $baris = DB::table('pretest as p')
                ->join('users as u', 'u.id', '=', 'p.user_id')
                ->join('tingkat_seleksi as t', 't.id', '=', 'p.tingkat_id')
                ->whereNotNull('p.nilai')
                ->when($tingkatId !== null, fn ($q) => $q->where('p.tingkat_id', $tingkatId))
                ->when($userId !== null, fn ($q) => $q->where('p.user_id', $userId))
                ->select('p.user_id', 'u.name as user_nama', 'p.tingkat_id', 't.nama_tingkat', 'p.nilai', 'p.disubmit_pada', 'p.created_at')
                ->orderBy('p.id')
                ->get();

            foreach ($baris as $r) {
                $hasil[] = [
                    'user_id' => (int) $r->user_id,
                    'user_nama' => (string) $r->user_nama,
                    'jenis' => 'pretest',
                    'target_id' => (int) $r->tingkat_id,
                    'target_nama' => 'Pre-Test '.$r->nama_tingkat,
                    'nilai' => (float) $r->nilai,
                    'waktu' => Carbon::parse($r->disubmit_pada ?? $r->created_at)->toIso8601String(),
                ];
            }
        }

        if ($peruntukan === null || $peruntukan === 'latihan') {
            $baris = DB::table('quiz_pengerjaan as qp')
                ->join('users as u', 'u.id', '=', 'qp.user_id')
                ->join('quiz as q', 'q.id', '=', 'qp.quiz_id')
                ->join('materi as m', 'm.id', '=', 'q.materi_id')
                ->whereNotNull('qp.nilai')
                ->when($tingkatId !== null, fn ($query) => $query->where('m.tingkat_id', $tingkatId))
                ->when($materiId !== null, fn ($query) => $query->where('q.materi_id', $materiId))
                ->when($userId !== null, fn ($query) => $query->where('qp.user_id', $userId))
                ->select('qp.user_id', 'u.name as user_nama', 'qp.quiz_id', 'q.nama_quiz', 'qp.nilai', 'qp.disubmit_pada', 'qp.created_at')
                ->orderBy('qp.id')
                ->get();

            foreach ($baris as $r) {
                $hasil[] = [
                    'user_id' => (int) $r->user_id,
                    'user_nama' => (string) $r->user_nama,
                    'jenis' => 'latihan',
                    'target_id' => (int) $r->quiz_id,
                    'target_nama' => (string) $r->nama_quiz,
                    'nilai' => (float) $r->nilai,
                    'waktu' => Carbon::parse($r->disubmit_pada ?? $r->created_at)->toIso8601String(),
                ];
            }
        }

        if (($peruntukan === null || $peruntukan === 'simulasi') && $materiId === null) {
            $baris = DB::table('hasil_simulasi as hs')
                ->join('users as u', 'u.id', '=', 'hs.user_id')
                ->join('simulasi as s', 's.id', '=', 'hs.simulasi_id')
                ->whereNotNull('hs.nilai')
                ->when($tingkatId !== null, fn ($q) => $q->where('s.tingkat_id', $tingkatId))
                ->when($userId !== null, fn ($q) => $q->where('hs.user_id', $userId))
                ->select('hs.user_id', 'u.name as user_nama', 'hs.simulasi_id', 's.nama_simulasi', 'hs.nilai', 'hs.disubmit_pada', 'hs.mulai_pada', 'hs.created_at')
                ->orderBy('hs.id')
                ->get();

            foreach ($baris as $r) {
                $hasil[] = [
                    'user_id' => (int) $r->user_id,
                    'user_nama' => (string) $r->user_nama,
                    'jenis' => 'simulasi',
                    'target_id' => (int) $r->simulasi_id,
                    'target_nama' => (string) $r->nama_simulasi,
                    'nilai' => (float) $r->nilai,
                    'waktu' => Carbon::parse($r->disubmit_pada ?? $r->mulai_pada ?? $r->created_at)->toIso8601String(),
                ];
            }
        }

        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriLog(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $logs = LogAktivitas::query()
            ->with('user:id,name,email')
            ->when($userId !== null, fn (Builder $q) => $q->where('user_id', $userId))
            ->whereBetween('terjadi_pada', [$dari, $sampai])
            ->orderByDesc('terjadi_pada')
            ->get();

        $entri = [];

        // Event akun dan admin digabung per user + aksi + tanggal agar daftar
        // tetap ringkas; rincian waktu tetap bisa dibuka lewat endpoint detail.
        $grup = $logs->groupBy(fn (LogAktivitas $log): string => implode('|', [
            $log->user_id ?? 'x',
            $log->aksi,
            $log->terjadi_pada?->toDateString(),
        ]));

        foreach ($grup as $daftar) {
            /** @var LogAktivitas $terakhir */
            $terakhir = $daftar->first();

            $entri[] = [
                'aksi' => $terakhir->aksi,
                'label' => $this->label($terakhir->aksi),
                'kategori' => $terakhir->kategori,
                'pada' => $terakhir->terjadi_pada?->toIso8601String(),
                'kali' => $daftar->sum(fn (LogAktivitas $log): int => (int) ($log->meta['jumlah'] ?? 1)),
                'objek' => $terakhir->meta['judul'] ?? null,
                'user_id' => $terakhir->user_id,
                'user_nama' => $terakhir->user?->name,
                'user_email' => $terakhir->user?->email,
            ];
        }

        return $entri;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriPretest(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('pretest as p')
            ->join('tingkat_seleksi as t', 't.id', '=', 'p.tingkat_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->when($userId !== null, fn ($q) => $q->where('p.user_id', $userId))
            ->where(function ($q) use ($dari, $sampai): void {
                $q->whereBetween('p.created_at', [$dari, $sampai])
                    ->orWhereBetween('p.disubmit_pada', [$dari, $sampai]);
            })
            ->select('p.user_id', 'u.name as user_nama', 'u.email as user_email',
                'p.created_at', 'p.disubmit_pada', 'p.nilai', 't.nama_tingkat')
            ->get();

        $entri = [];

        foreach ($baris as $row) {
            if ($this->dalamRentang($row->created_at, $dari, $sampai)) {
                $entri[] = $this->entri('pretest.mulai', 'Mengerjakan pretest', 'belajar', $row->created_at, $row->nama_tingkat, $this->infoUser($row));
            }

            if ($this->dalamRentang($row->disubmit_pada, $dari, $sampai)) {
                $entri[] = $this->entri('pretest.selesai', 'Menyelesaikan pretest', 'belajar', $row->disubmit_pada, $row->nama_tingkat, [
                    ...$this->infoUser($row),
                    'nilai' => $row->nilai !== null ? (float) $row->nilai : null,
                ]);
            }
        }

        return $entri;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriLatihan(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('quiz_pengerjaan as qp')
            ->join('quiz as q', 'q.id', '=', 'qp.quiz_id')
            ->join('users as u', 'u.id', '=', 'qp.user_id')
            ->when($userId !== null, fn ($q) => $q->where('qp.user_id', $userId))
            ->where(function ($q) use ($dari, $sampai): void {
                $q->whereBetween('qp.created_at', [$dari, $sampai])
                    ->orWhereBetween('qp.disubmit_pada', [$dari, $sampai]);
            })
            ->select('qp.user_id', 'u.name as user_nama', 'u.email as user_email',
                'qp.created_at', 'qp.disubmit_pada', 'qp.nilai', 'q.nama_quiz')
            ->get();

        $entri = [];

        foreach ($baris as $row) {
            if ($this->dalamRentang($row->created_at, $dari, $sampai)) {
                $entri[] = $this->entri('latihan.mulai', 'Mengerjakan latihan', 'belajar', $row->created_at, $row->nama_quiz, $this->infoUser($row));
            }

            if ($this->dalamRentang($row->disubmit_pada, $dari, $sampai)) {
                $entri[] = $this->entri('latihan.selesai', 'Menyelesaikan latihan', 'belajar', $row->disubmit_pada, $row->nama_quiz, [
                    ...$this->infoUser($row),
                    'nilai' => $row->nilai !== null ? (float) $row->nilai : null,
                ]);
            }
        }

        return $entri;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriSimulasi(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('hasil_simulasi as h')
            ->join('simulasi as s', 's.id', '=', 'h.simulasi_id')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->when($userId !== null, fn ($q) => $q->where('h.user_id', $userId))
            ->where(function ($q) use ($dari, $sampai): void {
                $q->whereBetween('h.mulai_pada', [$dari, $sampai])
                    ->orWhereBetween('h.disubmit_pada', [$dari, $sampai]);
            })
            ->select('h.user_id', 'u.name as user_nama', 'u.email as user_email',
                'h.mulai_pada', 'h.disubmit_pada', 'h.nilai', 'h.lulus', 's.nama_simulasi')
            ->get();

        $entri = [];

        foreach ($baris as $row) {
            if ($this->dalamRentang($row->mulai_pada, $dari, $sampai)) {
                $entri[] = $this->entri('simulasi.mulai', 'Mengerjakan simulasi', 'belajar', $row->mulai_pada, $row->nama_simulasi, $this->infoUser($row));
            }

            if ($this->dalamRentang($row->disubmit_pada, $dari, $sampai)) {
                $lulus = $row->lulus === null ? null : (bool) $row->lulus;
                $entri[] = $this->entri(
                    'simulasi.selesai',
                    $lulus === true ? 'Lulus simulasi' : ($lulus === false ? 'Tidak lulus simulasi' : 'Menyelesaikan simulasi'),
                    'belajar',
                    $row->disubmit_pada,
                    $row->nama_simulasi,
                    [
                        ...$this->infoUser($row),
                        'nilai' => $row->nilai !== null ? (float) $row->nilai : null,
                        'lulus' => $lulus,
                    ],
                );
            }
        }

        return $entri;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriMateri(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('progress_belajar as pb')
            ->join('materi as m', 'm.id', '=', 'pb.materi_id')
            ->join('users as u', 'u.id', '=', 'pb.user_id')
            ->when($userId !== null, fn ($q) => $q->where('pb.user_id', $userId))
            ->where('pb.status', 'selesai')
            ->whereNotNull('pb.tanggal_selesai')
            ->whereBetween('pb.tanggal_selesai', [$dari, $sampai])
            ->select('pb.user_id', 'u.name as user_nama', 'u.email as user_email',
                'm.urutan', 'm.judul', 'pb.tanggal_selesai')
            ->get();

        return $baris
            ->map(fn ($row): array => $this->entri(
                'materi.selesai',
                'Menyelesaikan materi',
                'belajar',
                $row->tanggal_selesai,
                'Materi '.$row->urutan.' — '.$row->judul,
                $this->infoUser($row),
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriSubmateri(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('progress_submateri as ps')
            ->join('progress_belajar as pb', 'pb.id', '=', 'ps.progress_belajar_id')
            ->join('materi as m', 'm.id', '=', 'pb.materi_id')
            ->join('users as u', 'u.id', '=', 'pb.user_id')
            ->when($userId !== null, fn ($q) => $q->where('pb.user_id', $userId))
            ->whereBetween('ps.selesai_pada', [$dari, $sampai])
            ->select('pb.user_id', 'u.name as user_nama', 'u.email as user_email',
                'ps.kode', 'ps.judul', 'ps.selesai_pada', 'm.urutan', 'm.judul as materi')
            ->get();

        return $baris
            ->map(fn ($row): array => $this->entri(
                'submateri.selesai',
                'Menyelesaikan submateri',
                'belajar',
                $row->selesai_pada,
                'Materi '.$row->urutan.' · '.$row->kode.' — '.$row->judul,
                $this->infoUser($row),
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriKenaikanTingkat(?int $userId, Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('kenaikan_tingkat as k')
            ->join('tingkat_seleksi as ta', 'ta.id', '=', 'k.tingkat_asal_id')
            ->leftJoin('tingkat_seleksi as tt', 'tt.id', '=', 'k.tingkat_tujuan_id')
            ->join('users as u', 'u.id', '=', 'k.user_id')
            ->when($userId !== null, fn ($q) => $q->where('k.user_id', $userId))
            ->whereBetween('k.created_at', [$dari, $sampai])
            ->select('k.user_id', 'u.name as user_nama', 'u.email as user_email',
                'k.status', 'k.created_at', 'ta.nama_tingkat as asal', 'tt.nama_tingkat as tujuan')
            ->get();

        return $baris
            ->map(function ($row): array {
                $objek = $row->tujuan !== null
                    ? $row->asal.' → '.$row->tujuan
                    : $row->asal;

                return $this->entri(
                    'tingkat.naik',
                    $row->status === 'lulus' ? 'Lulus kenaikan tingkat' : 'Tidak lulus kenaikan tingkat',
                    'belajar',
                    $row->created_at,
                    $objek,
                    [
                        ...$this->infoUser($row),
                        'lulus' => $row->status === 'lulus',
                    ],
                );
            })
            ->all();
    }

    /**
     * @return array{user_id: int|null, user_nama: string|null, user_email: string|null}
     */
    private function infoUser(mixed $row): array
    {
        return [
            'user_id' => $row->user_id ?? null,
            'user_nama' => $row->user_nama ?? null,
            'user_email' => $row->user_email ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function entri(string $aksi, string $label, string $kategori, mixed $waktu, ?string $objek = null, array $tambahan = []): array
    {
        $carbon = $waktu !== null ? Carbon::parse($waktu) : null;

        return [
            'aksi' => $aksi,
            'label' => $label,
            'kategori' => $kategori,
            'pada' => $carbon?->toIso8601String(),
            'kali' => 1,
            'objek' => $objek,
            ...$tambahan,
        ];
    }

    private function dalamRentang(mixed $waktu, Carbon $dari, Carbon $sampai): bool
    {
        if ($waktu === null) {
            return false;
        }

        return Carbon::parse($waktu)->between($dari, $sampai);
    }

    private function label(string $aksi): string
    {
        if (isset(self::LABEL[$aksi])) {
            return self::LABEL[$aksi];
        }

        [$entitas, $verba] = array_pad(explode('.', $aksi, 2), 2, '');
        $namaEntitas = self::ENTITAS[$entitas] ?? str_replace('_', ' ', $entitas);
        $namaVerba = self::VERBA[$verba] ?? ucfirst(str_replace('_', ' ', $verba));

        return trim($namaVerba.' '.$namaEntitas);
    }
}
