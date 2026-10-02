<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembahasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soal_id')->constrained('soal')->onDelete('cascade');
            $table->longText('isi_pembahasan');
            $table->unique('soal_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembahasan');
    }
};
