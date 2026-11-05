<?php

return [
    'tingkat' => ['kabupaten', 'provinsi'],

    // Kata yang dicari di kolom tingkat_seleksi.nama_tingkat (tidak peka huruf besar/kecil).
    // Cek nama aslinya: DB::table('tingkat_seleksi')->get() di php artisan tinker.
    'nama_tingkat' => [
        'kabupaten' => 'kabupaten',
        'provinsi' => 'provinsi',
    ],

    // Syarat masuk leaderboard: nilai pre-test terbaik minimal sekian (asumsi skala 0-100).
    'batas_pretest' => [
        'kabupaten' => 75,
        'provinsi' => 85,
    ],

    'limit' => 10,

    // Akun dengan role ini (spatie permission) tidak masuk leaderboard.
    'admin_role' => 'Super Admin',
    'user_model' => \App\Models\User::class,
];