<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nim', 20)->nullable();
        });

        // Unik hanya di antara akun yang belum dihapus, sama seperti users_email_aktif_unique.
        DB::statement('CREATE UNIQUE INDEX users_nim_aktif_unique ON users (nim) WHERE nim IS NOT NULL AND deleted_at IS NULL');
        // Kode anggota berbentuk OSN<NAMA> (bisa lebih dari 4 karakter); index unik kode_referal tetap utuh.
        DB::statement('ALTER TABLE users ALTER COLUMN kode_referal TYPE VARCHAR(40)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_nim_aktif_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('nim');
        });

        // Kode anggota akan terpotong jika kolom dikembali ke 4 karakter.
    }
};
