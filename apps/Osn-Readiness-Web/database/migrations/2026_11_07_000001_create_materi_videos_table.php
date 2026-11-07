<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('materi_id')->constrained('materi')->cascadeOnDelete();
            $table->string('judul', 160);
            $table->string('youtube_url', 2048);
            $table->string('youtube_id', 64);
            $table->enum('tipe', ['video', 'playlist'])->default('video');
            $table->unsignedInteger('urutan')->default(1);
            $table->timestamps();
            $table->index(['materi_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi_videos');
    }
};
