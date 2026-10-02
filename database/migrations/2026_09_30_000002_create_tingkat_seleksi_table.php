<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tingkat_seleksi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
            $table->string('deskripsi')->nullable();
            $table->integer('urutan')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tingkat_seleksi');
    }
};
