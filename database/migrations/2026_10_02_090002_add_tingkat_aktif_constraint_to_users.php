<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan foreign key constraint tingkat_aktif_id ke users.
     * Migration ini harus berjalan SETELAH 090001 (tingkat_seleksi tabel sudah ada).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'tingkat_aktif_id')) {
                $table->foreign('tingkat_aktif_id')
                    ->references('id')
                    ->on('tingkat_seleksi')
                    ->nullableOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeignKey('users_tingkat_aktif_id_foreign');
        });
    }
};
