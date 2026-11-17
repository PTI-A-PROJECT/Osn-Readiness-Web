<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas waktu pre-test disimpan di server.
 *
 * Sebelumnya durasi hanya dihitung di frontend dari konstanta 60 menit,
 * sehingga reload di tengah pengerjaan mengembalikan timer ke awal dan
 * pre-test praktis tanpa batas waktu. Kolom ini membuat sisa waktu
 * deterministik seperti simulasi (mulai_pada/batas_pada di hasil_simulasi).
 *
 * Pre-test lama (dibuat sebelum migrasi) bernilai NULL; frontend memakai
 * hitungan lokal sebagai fallback supaya pengerjaan yang sedang berjalan
 * tidak langsung dianggap habis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pretest', function (Blueprint $table): void {
            $table->timestampTz('mulai_pada')->nullable()->after('tingkat_id');
            $table->timestampTz('batas_pada')->nullable()->after('mulai_pada');
        });
    }

    public function down(): void
    {
        Schema::table('pretest', function (Blueprint $table): void {
            $table->dropColumn(['mulai_pada', 'batas_pada']);
        });
    }
};
