<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_belajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('materi_id')->constrained('materi')->onDelete('cascade');
            $table->string('status', 20)->default('belum_belajar'); // belum_belajar, sedang_belajar, selesai
            $table->timestamp('tanggal_selesai')->nullable();
            $table->integer('persentase')->default(0);
            $table->unique(['user_id', 'materi_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_belajar');
    }
};
