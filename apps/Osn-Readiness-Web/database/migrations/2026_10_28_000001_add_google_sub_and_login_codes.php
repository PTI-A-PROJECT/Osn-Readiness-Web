<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Pengenal akun tetap dari Google. Sub dijadikan pengenal, bukan
            // email, karena Google memungkinan email berubah. Softdelete-safe:
            // akun lama yang tidak lagi memakai Google tetap punya nilainya.
            $table->string('google_sub')->nullable()->index();
        });

        // Unique di PostgreSQL mengizinkan banyak NULL, jadi akun lokal
        // tanpa Google tidak bertabrakan. Unik karena satu akun Google
        // hanya boleh melekat pada satu akun (termasuk akun terhapus agar
        // ia tidak dapat di-link-kan ulang ke akun lain).
        DB::statement('CREATE UNIQUE INDEX users_google_sub_unique ON users (google_sub) WHERE google_sub IS NOT NULL');

        Schema::create('google_login_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kode_hash')->unique();
            $table->timestamp('kedaluwarsa_pada');
            $table->timestamp('dipakai_pada')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_login_codes');
        DB::statement('DROP INDEX IF EXISTS users_google_sub_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['google_sub']);
            $table->dropColumn('google_sub');
        });
    }
};
