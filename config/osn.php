<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masa retensi akun yang dihapus
    |--------------------------------------------------------------------------
    |
    | Akun yang sudah di-soft delete lebih lama dari angka ini (hari) akan
    | dihapus permanen oleh PurgeAkunJob, berikut seluruh data pengerjaannya
    | karena foreign key memakai cascadeOnDelete.
    |
    */

    'retensi_akun_hari' => (int) env('RETENSI_AKUN_HARI', 30),

    /*
    |--------------------------------------------------------------------------
    | Jumlah akun yang dihapus per satu potongan
    |--------------------------------------------------------------------------
    |
    | Menghapus akun ikut menghapus tabel jawaban, progress, dan riwayat, jadi
    | dikerjakan bertahap supaya tidak menahan kunci tabel terlalu lama.
    |
    */

    'purge_akun_chunk' => (int) env('PURGE_AKUN_CHUNK', 100),

];
