<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = <<<'SQL'
CREATE VIEW riwayat_hasil AS
SELECT
    pt.id,
    pt.user_id,
    'pretest' as jenis,
    ts.id as tingkat_id,
    ts.nama as tingkat_nama,
    pt.nilai,
    pt.selesai_pada as tanggal_selesai,
    pt.created_at,
    NULL::bigint as simulasi_id
FROM pretest pt
JOIN tingkat_seleksi ts ON pt.tingkat_seleksi_id = ts.id
WHERE pt.selesai_pada IS NOT NULL

UNION ALL

SELECT
    hs.id,
    hs.user_id,
    'simulasi' as jenis,
    ts.id as tingkat_id,
    ts.nama as tingkat_nama,
    hs.nilai,
    hs.selesai_pada as tanggal_selesai,
    hs.created_at,
    s.id as simulasi_id
FROM hasil_simulasi hs
JOIN simulasi s ON hs.simulasi_id = s.id
JOIN tingkat_seleksi ts ON s.tingkat_seleksi_id = ts.id
WHERE hs.selesai_pada IS NOT NULL

ORDER BY tanggal_selesai DESC
SQL;

        DB::statement($sql);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS riwayat_hasil');
    }
};
