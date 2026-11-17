"""Perhitungan psikometri Teori Tes Klasik (Classical Test Theory / CTT),
persentase kemunculan soal (Item Exposure Rate & Inverse Weighting), serta
analisis nilai percobaan berulang (Attempt 1 vs Attempt 2+).
"""

from __future__ import annotations

import math
from collections import defaultdict
from collections.abc import Sequence

from data_analytics.schemas import (
    AnalisisAttemptItem,
    AnalisisAttemptRingkasan,
    AnalisisButirItem,
    AnalisisKlasikRequest,
    AnalisisKlasikResponse,
    AnalisisReliabilitasRingkasan,
)


def _label_kesukaran(p: float) -> str:
    if p < 0.30:
        return "Sukar"
    if p <= 0.70:
        return "Sedang (Ideal)"
    return "Mudah"


def _label_daya_beda(d: float) -> str:
    if d >= 0.40:
        return "Sangat Baik"
    if d >= 0.30:
        return "Baik"
    if d >= 0.20:
        return "Cukup (Perlu Revisi)"
    if d >= 0.0:
        return "Buruk (Revisi/Buang)"
    return "Negatif (Cek Kunci)"


def _label_validitas(r_pbis: float) -> tuple[bool, str]:
    if r_pbis >= 0.30:
        return True, "Valid (Tinggi)"
    if r_pbis >= 0.20:
        return True, "Valid (Cukup)"
    if r_pbis > 0.0:
        return False, "Kurang Valid"
    return False, "Tidak Valid"


def _label_reliabilitas(kr20: float, jumlah_sesi: int) -> str:
    if jumlah_sesi < 2:
        return "Belum Cukup Data"
    if kr20 >= 0.80:
        return "Sangat Tinggi (Sangat Reliabel)"
    if kr20 >= 0.70:
        return "Tinggi (Reliabel)"
    if kr20 >= 0.50:
        return "Sedang (Cukup Reliabel)"
    return "Rendah (Kurang Reliabel)"


def _rekomendasi_butir(
    jumlah_menjawab: int,
    p: float,
    d: float,
    valid: bool,
) -> str:
    if jumlah_menjawab < 2:
        return "Belum Cukup Data"
    if d < 0.0:
        return "Cacat / Cek Kunci Jawaban"
    if valid and d >= 0.30 and 0.20 <= p <= 0.85:
        return "Soal Baik (Pertahankan)"
    if d >= 0.20 or (valid and 0.15 <= p <= 0.90):
        return "Perlu Revisi Ringan"
    return "Perlu Direvisi / Diganti"


def hitung_analisis_klasik(req: AnalisisKlasikRequest) -> AnalisisKlasikResponse:
    """Hitung 4 tes klasik (Reliabilitas KR-20, Validitas Point-Biserial,
    Daya Pembeda 27%, Tingkat Kesukaran), persentase kali keluar & bobot
    peluang soal, serta analisis percobaan ke-1 vs ke-2+.
    """
    total_sesi = len(req.sesi)

    # 1. Urutkan sesi dari nilai tertinggi ke terendah untuk pengelompokan 27%
    sesi_terurut = sorted(
        req.sesi,
        key=lambda s: (s.nilai_total, -s.sesi_id),
        reverse=True,
    )

    n_kelompok = max(1, round(0.27 * total_sesi)) if total_sesi >= 2 else 0
    sesi_atas_ids = {s.sesi_id for s in sesi_terurut[:n_kelompok]}
    sesi_bawah_ids = {s.sesi_id for s in sesi_terurut[-n_kelompok:]} if n_kelompok > 0 else set()

    skor_per_sesi = {s.sesi_id: s.nilai_total for s in req.sesi}

    # Kumpulkan jawaban per soal_id: list[(sesi_id, benar)]
    jawaban_per_soal: dict[int, list[tuple[int, bool]]] = defaultdict(list)
    for s in req.sesi:
        for j in s.jawaban:
            jawaban_per_soal[j.soal_id].append((s.sesi_id, j.status_benar))

    # 2. Hitung bobot peluang keluar: w_i = 1 / (jumlah_keluar + 1)
    # dikelompokkan per (peruntukan, level) agar persentase peluang berikutnya
    # proporsional di dalam pool pemilihannya.
    total_bobot_pool: dict[tuple[str, str], float] = defaultdict(float)
    bobot_per_soal: dict[int, float] = {}
    for b in req.bank_soal:
        frekuensi = max(0, b.jumlah_keluar)
        bobot = 1.0 / (frekuensi + 1)
        bobot_per_soal[b.soal_id] = bobot
        total_bobot_pool[(b.peruntukan, b.level)] += bobot

    # 3. Analisis per butir soal + kumpulkan komponen p*q untuk KR-20
    butir_items: list[AnalisisButirItem] = []
    pq_list: list[float] = []

    for b in req.bank_soal:
        catatan = jawaban_per_soal.get(b.soal_id, [])
        n_jawab = len(catatan)
        n_benar = sum(1 for _, benar in catatan if benar)

        # a) Tingkat Kesukaran (P)
        p = round(n_benar / n_jawab, 4) if n_jawab > 0 else 0.0
        q = 1.0 - p
        if n_jawab >= 2:
            pq_list.append(p * q)

        # b) Daya Pembeda (D) dengan metode Upper 27% vs Lower 27%
        atas = [benar for sesi_id, benar in catatan if sesi_id in sesi_atas_ids]
        bawah = [benar for sesi_id, benar in catatan if sesi_id in sesi_bawah_ids]
        if atas and bawah:
            p_atas = sum(1 for x in atas if x) / len(atas)
            p_bawah = sum(1 for x in bawah if x) / len(bawah)
            d = round(p_atas - p_bawah, 4)
        elif n_jawab >= 2:
            # Fallback jika sampel soal belum terdistribusi di grup 27% ekstrem:
            # bagi 50% atas vs 50% bawah dari penjawab soal tersebut
            catatan_urut = sorted(
                catatan, key=lambda item: skor_per_sesi.get(item[0], 0.0), reverse=True
            )
            paruh = max(1, len(catatan_urut) // 2)
            grup_a = catatan_urut[:paruh]
            grup_b = catatan_urut[-paruh:]
            p_a = sum(1 for _, ok in grup_a if ok) / len(grup_a)
            p_b = sum(1 for _, ok in grup_b if ok) / len(grup_b)
            d = round(p_a - p_b, 4)
        else:
            d = 0.0

        # c) Validitas Butir (Point-Biserial Correlation r_pbis)
        if n_jawab >= 2 and 0.0 < p < 1.0:
            skor_penjawab = [skor_per_sesi[sesi_id] for sesi_id, _ in catatan]
            mean_total = sum(skor_penjawab) / n_jawab
            varians_total = sum((x - mean_total) ** 2 for x in skor_penjawab) / n_jawab
            std_total = math.sqrt(varians_total)
            if std_total > 1e-9:
                skor_benar = [skor_per_sesi[sesi_id] for sesi_id, ok in catatan if ok]
                mean_benar = sum(skor_benar) / len(skor_benar)
                r_pbis_raw = ((mean_benar - mean_total) / std_total) * math.sqrt(p / q)
                r_pbis = round(max(-1.0, min(1.0, r_pbis_raw)), 4)
            else:
                r_pbis = 0.0
        else:
            r_pbis = 0.0

        valid, label_val = _label_validitas(r_pbis)
        label_sukar = _label_kesukaran(p) if n_jawab > 0 else "Belum Diujikan"
        label_beda = _label_daya_beda(d) if n_jawab >= 2 else "Belum Cukup Data"
        if n_jawab < 2:
            label_val = "Belum Cukup Data"

        # Persentase kemunculan & peluang keluar berikutnya
        frek = max(0, b.jumlah_keluar)
        persen_keluar = round((frek / total_sesi) * 100, 2) if total_sesi > 0 else 0.0
        bobot = bobot_per_soal[b.soal_id]
        tot_pool = total_bobot_pool[(b.peruntukan, b.level)]
        peluang_berikutnya = round((bobot / tot_pool) * 100, 2) if tot_pool > 0 else 0.0

        butir_items.append(
            AnalisisButirItem(
                soal_id=b.soal_id,
                kode_soal=b.kode_soal,
                pertanyaan_ringkas=b.pertanyaan_ringkas,
                materi_id=b.materi_id,
                materi_judul=b.materi_judul,
                level=b.level,
                peruntukan=b.peruntukan,
                jumlah_keluar=frek,
                persentase_keluar=persen_keluar,
                bobot_peluang=round(bobot, 4),
                peluang_keluar_berikutnya=peluang_berikutnya,
                jumlah_menjawab=n_jawab,
                jumlah_benar=n_benar,
                tingkat_kesukaran=p,
                kategori_kesukaran=label_sukar,
                daya_pembeda=d,
                kategori_daya_pembeda=label_beda,
                validitas_rpbis=r_pbis,
                valid=valid if n_jawab >= 2 else False,
                kategori_validitas=label_val,
                rekomendasi=_rekomendasi_butir(n_jawab, p, d, valid),
            )
        )

    # 4. Reliabilitas Tes (KR-20)
    k = len(pq_list)
    if total_sesi >= 2 and k >= 2:
        # Gunakan jumlah benar per sesi pada soal yang dianalisis (atau skor total)
        jumlah_benar_sesi = [
            sum(1 for j in s.jawaban if j.status_benar) for s in req.sesi
        ]
        mean_jb = sum(jumlah_benar_sesi) / total_sesi
        var_jb = sum((x - mean_jb) ** 2 for x in jumlah_benar_sesi) / total_sesi
        sum_pq = sum(pq_list)

        if var_jb > 1e-9 and var_jb >= sum_pq / k:
            kr20_raw = (k / (k - 1)) * (1.0 - (sum_pq / var_jb))
            kr20 = round(max(0.0, min(1.0, kr20_raw)), 4)
        else:
            # Fallback menggunakan varians skor_total terstandarisasi ke skala k
            skor_norm = [(s.nilai_total / 100.0) * k for s in req.sesi]
            mean_sn = sum(skor_norm) / total_sesi
            var_sn = sum((x - mean_sn) ** 2 for x in skor_norm) / total_sesi
            if var_sn > 1e-9:
                kr20_raw = (k / (k - 1)) * (1.0 - (sum_pq / var_sn))
                kr20 = round(max(0.0, min(1.0, kr20_raw)), 4)
            else:
                kr20 = 0.0
    else:
        kr20 = 0.0

    butir_teruji = [b for b in butir_items if b.jumlah_menjawab >= 2]
    rata_p = (
        round(sum(b.tingkat_kesukaran for b in butir_teruji) / len(butir_teruji), 4)
        if butir_teruji
        else 0.0
    )
    rata_d = (
        round(sum(b.daya_pembeda for b in butir_teruji) / len(butir_teruji), 4)
        if butir_teruji
        else 0.0
    )
    rata_r = (
        round(sum(b.validitas_rpbis for b in butir_teruji) / len(butir_teruji), 4)
        if butir_teruji
        else 0.0
    )

    ringkasan_reliabilitas = AnalisisReliabilitasRingkasan(
        kr20=kr20,
        kategori=_label_reliabilitas(kr20, total_sesi),
        jumlah_sesi=total_sesi,
        jumlah_soal_teruji=len(butir_teruji),
        jumlah_soal_valid=sum(1 for b in butir_teruji if b.valid),
        jumlah_soal_baik=sum(
            1 for b in butir_teruji if b.rekomendasi == "Soal Baik (Pertahankan)"
        ),
        rata_kesukaran=rata_p,
        rata_daya_pembeda=rata_d,
        rata_validitas=rata_r,
    )

    # 5. Analisis Percobaan (Attempt 1 vs Attempt 2 vs Attempt 3+)
    grup_attempt: dict[tuple[int, str, int], list[tuple[str, float, str, str]]] = (
        defaultdict(list)
    )
    for p_item in req.pengerjaan:
        kunci = (p_item.user_id, p_item.jenis, p_item.target_id)
        grup_attempt[kunci].append(
            (p_item.waktu, p_item.nilai, p_item.user_nama, p_item.target_nama)
        )

    attempt_items: list[AnalisisAttemptItem] = []
    skor_attempt_1: list[float] = []
    skor_attempt_2: list[float] = []
    skor_attempt_3_plus: list[float] = []
    delta_list: list[float] = []
    meningkat_count = 0

    for (uid, jenis, tid), daftar in grup_attempt.items():
        # Urutkan kronologis dari percobaan pertama ke terakhir
        daftar_urut = sorted(daftar, key=lambda x: x[0])
        user_nama = daftar_urut[-1][2]
        target_nama = daftar_urut[-1][3]
        nilai_list = [round(x[1], 2) for x in daftar_urut]

        n1 = nilai_list[0]
        skor_attempt_1.append(n1)

        n2 = nilai_list[1] if len(nilai_list) >= 2 else None
        if n2 is not None:
            skor_attempt_2.append(n2)
            selisih = round(n2 - n1, 2)
            delta_list.append(selisih)
            if selisih > 0:
                meningkat_count += 1
        else:
            selisih = None

        n3_plus: float | None = None
        if len(nilai_list) >= 3:
            sisa = nilai_list[2:]
            skor_attempt_3_plus.extend(sisa)
            n3_plus = round(sum(sisa) / len(sisa), 2)

        attempt_items.append(
            AnalisisAttemptItem(
                user_id=uid,
                user_nama=user_nama,
                jenis=jenis,
                target_id=tid,
                target_nama=target_nama,
                jumlah_percobaan=len(nilai_list),
                nilai_percobaan_1=n1,
                nilai_percobaan_2=n2,
                nilai_percobaan_3_plus=n3_plus,
                nilai_terbaik=max(nilai_list),
                selisih_1_ke_2=selisih,
                riwayat_nilai=nilai_list,
            )
        )

    # Urutkan sehingga yang punya >= 2 percobaan tampil paling atas
    attempt_items.sort(
        key=lambda item: (item.jumlah_percobaan >= 2, item.jumlah_percobaan, item.nilai_terbaik),
        reverse=True,
    )

    ringkasan_attempt = AnalisisAttemptRingkasan(
        total_kombinasi=len(attempt_items),
        jumlah_coba_sekali=sum(1 for a in attempt_items if a.jumlah_percobaan == 1),
        jumlah_coba_dua_kali=sum(1 for a in attempt_items if a.jumlah_percobaan == 2),
        jumlah_coba_tiga_plus=sum(1 for a in attempt_items if a.jumlah_percobaan >= 3),
        rata_nilai_percobaan_1=round(sum(skor_attempt_1) / len(skor_attempt_1), 2)
        if skor_attempt_1
        else 0.0,
        rata_nilai_percobaan_2=round(sum(skor_attempt_2) / len(skor_attempt_2), 2)
        if skor_attempt_2
        else 0.0,
        rata_nilai_percobaan_3_plus=round(
            sum(skor_attempt_3_plus) / len(skor_attempt_3_plus), 2
        )
        if skor_attempt_3_plus
        else 0.0,
        rata_peningkatan_1_ke_2=round(sum(delta_list) / len(delta_list), 2)
        if delta_list
        else 0.0,
        persen_siswa_meningkat=round((meningkat_count / len(delta_list)) * 100, 2)
        if delta_list
        else 0.0,
    )

    return AnalisisKlasikResponse(
        reliabilitas=ringkasan_reliabilitas,
        butir_soal=butir_items,
        ringkasan_percobaan=ringkasan_attempt,
        rincian_percobaan=attempt_items,
    )
