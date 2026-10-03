<?php

use App\Http\Controllers\Api\Admin\KompetensiController;
use App\Http\Controllers\Api\Admin\KonteksSoalController;
use App\Http\Controllers\Api\Admin\MateriController;
use App\Http\Controllers\Api\Admin\SiswaController;
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
});

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
| Semua admin route dilindungi dengan role:Super Admin middleware.
| Hanya Super Admin yang bisa mengakses semua fitur admin.
*/

Route::middleware(['auth:sanctum', 'active', 'super_admin'])->prefix('admin')->group(function (): void {
    // [B1-D] Admin Struktur Konten
    Route::apiResource('tingkat', App\Http\Controllers\Api\Admin\TingkatController::class);
    Route::apiResource('kompetensi', KompetensiController::class);
    Route::apiResource('materi', MateriController::class);
    Route::post('materi/{materi}/upload-image', [MateriController::class, 'uploadImage']);
    Route::apiResource('konteks-soal', KonteksSoalController::class);
    Route::apiResource('siswa', SiswaController::class);
    Route::post('siswa/{siswa}/deactivate', [SiswaController::class, 'deactivate']);

    // [B1-E] Admin Soal - to be added
    // [B1-F] Admin Latihan - to be added
    // [B1-G] Admin Simulasi - to be added
    // [B1-H] Admin Dashboard - to be added
});
