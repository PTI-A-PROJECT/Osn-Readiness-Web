<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_submateri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('progress_belajar_id')
                ->constrained('progress_belajar')
                ->cascadeOnDelete();
            $table->string('kode', 120);
            $table->string('judul');
            $table->timestampTz('selesai_pada');
            $table->timestampsTz();
            $table->unique(['progress_belajar_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_submateri');
    }
};
