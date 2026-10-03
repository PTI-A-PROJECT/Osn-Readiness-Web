<?php

use App\Http\Controllers\Api\Admin\AturanPemetaanController;
use App\Http\Controllers\Api\Admin\LatihanController;
use App\Http\Controllers\Api\Admin\PembahasanController;
use App\Http\Controllers\Api\Admin\SimulasiController;
use App\Http\Controllers\Api\Admin\SoalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PretestController;
use App\Http\Controllers\Api\TingkatController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute publik
|--------------------------------------------------------------------------
| Registrasi dan login tidak butuh token. Login dibatasi lima kali per menit
| per email + IP lewat named limiter "login" (lihat AppServiceProvider).
*/

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
});

/*
|--------------------------------------------------------------------------
| Rute siswa
|--------------------------------------------------------------------------
| Semua rute di sini berada di balik auth:sanctum + active, sehingga akun yang
| dinonaktifkan di tengah sesi langsung tertolak.
|
| Blok per paket; hanya isi blok milik paket yang sedang dikerjakan supaya
| konflik merge antar orang tetap kecil.
*/

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // [B1-A] Akses tingkat dan status putaran.
    Route::get('tingkat', [TingkatController::class, 'index']);

    // [B2-A] Pre-test dan pemetaan.
    Route::post('pretest', [PretestController::class, 'store']);
    Route::get('pretest/{pretest}', [PretestController::class, 'show']);
    Route::put('pretest/{pretest}/jawaban', [PretestController::class, 'simpanJawaban']);
    Route::post('pretest/{pretest}/submit', [PretestController::class, 'submit']);

    // [B2-B] Materi, progress, latihan, dan syarat simulasi.
    // [B3-A] Simulasi, kelulusan, dan review.
    // [B3-B] Dashboard dan riwayat.

    // Modul users lama. B1-D memindahkannya ke /api/admin/siswa.
    Route::apiResource('users', UserController::class);

    // [B1-E] Admin soal & konfigurasi
    Route::prefix('admin')->middleware('role:Super Admin')->group(function (): void {
        // Soal CRUD
        Route::apiResource('soal', SoalController::class);

        // Pembahasan upsert per soal
        Route::post('soal/{soal}/pembahasan', [PembahasanController::class, 'store']);
        Route::put('soal/{soal}/pembahasan', [PembahasanController::class, 'update']);
        Route::get('soal/{soal}/pembahasan', [PembahasanController::class, 'show']);
        Route::delete('soal/{soal}/pembahasan', [PembahasanController::class, 'destroy']);

        // Latihan (Quiz) CRUD
        Route::apiResource('latihan', LatihanController::class);

        // Simulasi CRUD
        Route::apiResource('simulasi', SimulasiController::class);

        // Aturan pemetaan per tingkat
        Route::get('tingkat/{tingkat}/aturan-pemetaan', [AturanPemetaanController::class, 'show']);
        Route::put('tingkat/{tingkat}/aturan-pemetaan', [AturanPemetaanController::class, 'update']);
    });
});
