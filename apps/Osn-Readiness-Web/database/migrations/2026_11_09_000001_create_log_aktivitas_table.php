<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table): void {
            $table->id();
            // nullOnDelete: log tetap tersimpan walau akunnya dihapus permanen.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 60);
            $table->string('kategori', 20)->default('akun');
            $table->string('subjek_tipe')->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('terjadi_pada')->useCurrent();

            // Dipakai timeline per user dan grouping per hari.
            $table->index(['user_id', 'terjadi_pada']);
            $table->index(['aksi', 'terjadi_pada']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
