<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kompetensi_id')->constrained('kompetensi')->onDelete('cascade');
            $table->string('judul');
            $table->longText('isi_materi');
            $table->integer('urutan');
            $table->string('id_sumber')->nullable();
            $table->unique(['kompetensi_id', 'urutan']);
            $table->unique('id_sumber');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
