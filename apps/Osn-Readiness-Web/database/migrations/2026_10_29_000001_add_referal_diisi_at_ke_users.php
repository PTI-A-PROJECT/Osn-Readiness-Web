<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null = akun masih berhak mengisi kode referal (hanya akun Google
            // baru). Terisi = kode sudah diisi atau dilewati; tidak bisa diubah.
            $table->timestamp('referal_diisi_at')->nullable()->after('direferensikan_oleh');
        });

        // Akun yang sudah ada tidak boleh diberi opsi input kode lagi.
        DB::table('users')->whereNull('referal_diisi_at')->update(['referal_diisi_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referal_diisi_at');
        });
    }
};
