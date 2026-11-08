<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leaderboard menyaring quiz per tingkat lalu DISTINCT ON (user_id,
        // quiz_id); arah (quiz_id, user_id) melengkapi index (user_id,
        // quiz_id) yang sudah ada dan dipakai saat join dari quiz.
        Schema::table('quiz_pengerjaan', function (Blueprint $table): void {
            $table->index(['quiz_id', 'user_id']);
        });

        // CTE lolos mengelompokkan nilai per tingkat_id; index (user_id,
        // tingkat_id) yang ada tidak terpakai saat tingkat_id jadi kolom
        // pemimpin.
        Schema::table('pretest', function (Blueprint $table): void {
            $table->index(['tingkat_id', 'user_id']);
        });

        // PostgreSQL tidak membuat index untuk foreign key. simulasi_id dipakai
        // join view riwayat_hasil dan pengecekan restrict saat simulasi dihapus.
        // pretest_id dipakai hitungan percobaan/nilai terbaik per putaran;
        // index (user_id, pretest_id) yang ada tidak melayani filter pretest_id
        // saja.
        Schema::table('hasil_simulasi', function (Blueprint $table): void {
            $table->index('simulasi_id');
            $table->index('pretest_id');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_pengerjaan', function (Blueprint $table): void {
            $table->dropIndex(['quiz_id', 'user_id']);
        });

        Schema::table('pretest', function (Blueprint $table): void {
            $table->dropIndex(['tingkat_id', 'user_id']);
        });

        Schema::table('hasil_simulasi', function (Blueprint $table): void {
            $table->dropIndex(['simulasi_id']);
            $table->dropIndex(['pretest_id']);
        });
    }
};
