<?php

namespace App\Console\Commands;

use App\Models\LogAktivitas;
use App\Services\AktivitasService;
use Illuminate\Console\Command;

class PruneLogAktivitas extends Command
{
    protected $signature = 'aktivitas:prune {--hari=90 : Retensi log dalam hari}';

    protected $description = 'Menghapus log aktivitas yang lebih lama dari masa retensi';

    public function handle(): int
    {
        $hari = max(1, (int) $this->option('hari'));
        $batas = now()->subDays($hari ?: AktivitasService::RETENSI_HARI);

        $jumlah = LogAktivitas::query()
            ->where('terjadi_pada', '<', $batas)
            ->delete();

        $this->info("Menghapus {$jumlah} log aktivitas yang lebih lama dari {$hari} hari.");

        return self::SUCCESS;
    }
}
