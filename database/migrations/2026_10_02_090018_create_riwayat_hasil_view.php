<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Buat view riwayat_hasil yang menggabung ketiga jenis pengerjaan:
     * pretest, latihan (quiz_pengerjaan), dan simulasi (hasil_simulasi).
     *
     * View ini hanya dibaca, migration-nya harus berjalan paling akhir setelah semua tabel ada.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE VIEW riwayat_hasil AS
            -- Pretest yang sudah selesai
            SELECT
                p.user_id,
                p.tingkat_id,
                \'pretest\' as jenis_hasil,
                p.nilai,
                p.selesai_pada as tanggal
            FROM pretest p
            WHERE p.selesai_pada IS NOT NULL

            UNION ALL

            -- Latihan (quiz pengerjaan) yang sudah selesai
            SELECT
                qp.user_id,
                m.tingkat_id,
                \'latihan\' as jenis_hasil,
                qp.nilai,
                qp.selesai_pada as tanggal
            FROM quiz_pengerjaan qp
            JOIN quiz q ON qp.quiz_id = q.id
            JOIN materi m ON q.materi_id = m.id
            WHERE qp.selesai_pada IS NOT NULL

            UNION ALL

            -- Simulasi yang sudah selesai
            SELECT
                hs.user_id,
                p.tingkat_id,
                \'simulasi\' as jenis_hasil,
                hs.nilai,
                hs.selesai_pada as tanggal
            FROM hasil_simulasi hs
            JOIN pretest p ON hs.pretest_id = p.id
            WHERE hs.selesai_pada IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS riwayat_hasil');
    }
};
