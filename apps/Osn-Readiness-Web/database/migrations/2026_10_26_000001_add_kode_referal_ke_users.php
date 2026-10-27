<?php

use App\Models\User;
use App\Support\KodeReferal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('kode_referal', 4)->nullable()->unique();
            $table->foreignId('direferensikan_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->index('direferensikan_oleh');
        });

        // Backfill: akun yang terdaftar sebelum kolom ini ada tetap mendapat
        // kode. Idempoten — hanya baris yang masih kosong yang diisi, jadi
        // migrasi yang diulang tidak mengubah kode lama.
        User::query()->whereNull('kode_referal')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->forceFill(['kode_referal' => KodeReferal::buatUnik()])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['direferensikan_oleh']);
            $table->dropIndex(['direferensikan_oleh']);
            $table->dropUnique(['kode_referal']);
            $table->dropColumn(['kode_referal', 'direferensikan_oleh']);
        });
    }
};
