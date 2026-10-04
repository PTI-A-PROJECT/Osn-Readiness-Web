<?php

use App\Jobs\PurgeAkunJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Command closures
|--------------------------------------------------------------------------
*/

// Menghapus akun yang sudah di-soft delete melewati masa retensi, berikut
// seluruh data pengerjaannya. withoutOverlapping supaya tidak berjalan dua
// kali bila satu siklus masih berjalan.

// [B3-C] Purge akun.
Schedule::job(new PurgeAkunJob)->dailyAt('03:00')->withoutOverlapping();

// [B3-A] Menutup percobaan simulasi yang batas waktunya sudah lewat tetapi
// belum disubmit. Jawaban yang sudah tersimpan dinilai; yang kosong
// dihitung salah. withoutOverlapping supaya dua siklus tidak menutup baris
// yang sama secara bersamaan.
Schedule::command('simulasi:tutup-kedaluwarsa')->everyMinute()->withoutOverlapping();
