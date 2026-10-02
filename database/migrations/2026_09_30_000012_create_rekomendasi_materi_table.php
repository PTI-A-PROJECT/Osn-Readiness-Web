<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekomendasi_materi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pretest_id')->constrained('pretest')->onDelete('cascade');
            $table->foreignId('materi_id')->constrained('materi')->onDelete('cascade');
            $table->boolean('wajib');
            $table->integer('prioritas')->default(0);
            $table->unique(['pretest_id', 'materi_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_materi');
    }
};
