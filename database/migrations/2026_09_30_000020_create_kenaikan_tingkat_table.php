<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kenaikan_tingkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tingkat_asal_id')->constrained('tingkat_seleksi')->onDelete('cascade');
            $table->foreignId('tingkat_tujuan_id')->nullable()->constrained('tingkat_seleksi')->nullOnDelete();
            $table->string('status', 20); // lulus, tidak_lulus
            $table->decimal('nilai_terbaik', 5, 2)->nullable();
            $table->integer('passing_grade')->nullable();
            $table->text('keterangan')->nullable();
            $table->unique(['user_id', 'tingkat_asal_id', 'status'], 'kenaikan_lulus_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kenaikan_tingkat');
    }
};
