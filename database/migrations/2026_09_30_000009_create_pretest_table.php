<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pretest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tingkat_seleksi_id')->constrained('tingkat_seleksi')->onDelete('cascade');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamp('disubmit_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->unique(['user_id', 'tingkat_seleksi_id', 'selesai_pada'], 'pretest_berjalan_unique')->where('selesai_pada', null);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pretest');
    }
};
