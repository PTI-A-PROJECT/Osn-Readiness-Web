<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kompetensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tingkat_seleksi_id')->constrained('tingkat_seleksi')->onDelete('cascade');
            $table->string('nama');
            $table->unique(['tingkat_seleksi_id', 'nama']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompetensi');
    }
};
