<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom tingkat_aktif_id dengan foreign key constraint ke users.
     * Migration ini harus berjalan SETELAH 090001 (tingkat_seleksi tabel sudah ada).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tingkat_aktif_id')
                ->nullable()
                ->constrained('tingkat_seleksi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['tingkat_aktif_id']);
            $table->dropColumn('tingkat_aktif_id');
        });
    }
};
