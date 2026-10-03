<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_simulasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('simulasi_id')->constrained('simulasi')->onDelete('cascade');
            $table->foreignId('pretest_id')->constrained('pretest')->onDelete('cascade');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->integer('jumlah_benar')->nullable();
            $table->integer('jumlah_salah')->nullable();
            $table->boolean('lulus')->nullable();
            $table->timestamp('mulai_pada');
            $table->timestamp('batas_pada');
            $table->timestamp('disubmit_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_simulasi');
    }
};
