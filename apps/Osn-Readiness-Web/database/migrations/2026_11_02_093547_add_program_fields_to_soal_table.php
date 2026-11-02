<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal', function (Blueprint $table): void {
            $table->text('pengantar')->nullable();
            $table->text('kode')->nullable();
            $table->string('bahasa', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table): void {
            $table->dropColumn([
                'pengantar',
                'kode',
                'bahasa',
            ]);
        });
    }
};
