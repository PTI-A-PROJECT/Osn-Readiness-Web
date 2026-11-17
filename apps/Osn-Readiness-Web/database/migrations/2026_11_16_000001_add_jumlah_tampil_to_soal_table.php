<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitungan global berapa kali satu soal sudah dikirim ke siswa.
 *
 * Dinaikkan sekali per paket pengerjaan saat paket dibuat (pre-test, latihan,
 * simulasi). Dipakai SoalPickerService untuk memprioritaskan soal yang paling
 * jarang keluar. Tanpa tabel log: kebutuhan saat ini hanya angka agregat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal', function (Blueprint $table): void {
            $table->unsignedBigInteger('jumlah_tampil')->default(0)->after('peruntukan');

            // Menunjang ORDER BY pada kandidat yang sudah difilter
            // tingkat + peruntukan.
            $table->index(['tingkat_id', 'peruntukan', 'jumlah_tampil']);
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table): void {
            $table->dropIndex(['tingkat_id', 'peruntukan', 'jumlah_tampil']);
            $table->dropColumn('jumlah_tampil');
        });
    }
};
