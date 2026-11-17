from __future__ import annotations

from data_analytics.klasik import hitung_analisis_klasik
from data_analytics.schemas import (
    AnalisisBankSoalItem,
    AnalisisJawabanSesiItem,
    AnalisisKlasikRequest,
    AnalisisPengerjaanAttemptInput,
    AnalisisSesiItem,
)


def test_hitung_analisis_klasik_empat_tes_dan_exposure_serta_attempt() -> None:
    bank_soal = [
        AnalisisBankSoalItem(
            soal_id=1,
            kode_soal="ALG-001",
            pertanyaan_ringkas="Soal ideal sedang",
            materi_id=1,
            materi_judul="Algoritma",
            level="sedang",
            peruntukan="simulasi",
            jumlah_keluar=4,
        ),
        AnalisisBankSoalItem(
            soal_id=2,
            kode_soal="ALG-002",
            pertanyaan_ringkas="Soal belum pernah keluar",
            materi_id=1,
            materi_judul="Algoritma",
            level="sedang",
            peruntukan="simulasi",
            jumlah_keluar=0,
        ),
    ]

    # 4 sesi: 2 skor tinggi menjawab benar soal 1, 2 skor rendah menjawab salah soal 1
    sesi = [
        AnalisisSesiItem(
            sesi_id=1,
            user_id=10,
            nilai_total=90.0,
            jawaban=[
                AnalisisJawabanSesiItem(soal_id=1, status_benar=True),
                AnalisisJawabanSesiItem(soal_id=2, status_benar=True),
            ],
        ),
        AnalisisSesiItem(
            sesi_id=2,
            user_id=11,
            nilai_total=85.0,
            jawaban=[
                AnalisisJawabanSesiItem(soal_id=1, status_benar=True),
                AnalisisJawabanSesiItem(soal_id=2, status_benar=True),
            ],
        ),
        AnalisisSesiItem(
            sesi_id=3,
            user_id=12,
            nilai_total=40.0,
            jawaban=[
                AnalisisJawabanSesiItem(soal_id=1, status_benar=False),
                AnalisisJawabanSesiItem(soal_id=2, status_benar=False),
            ],
        ),
        AnalisisSesiItem(
            sesi_id=4,
            user_id=13,
            nilai_total=30.0,
            jawaban=[
                AnalisisJawabanSesiItem(soal_id=1, status_benar=False),
                AnalisisJawabanSesiItem(soal_id=2, status_benar=False),
            ],
        ),
    ]

    pengerjaan = [
        AnalisisPengerjaanAttemptInput(
            user_id=10,
            user_nama="Budi",
            jenis="simulasi",
            target_id=1,
            target_nama="Simulasi 1",
            nilai=60.0,
            waktu="2026-11-01T10:00:00",
        ),
        AnalisisPengerjaanAttemptInput(
            user_id=10,
            user_nama="Budi",
            jenis="simulasi",
            target_id=1,
            target_nama="Simulasi 1",
            nilai=85.0,
            waktu="2026-11-02T10:00:00",
        ),
    ]

    hasil = hitung_analisis_klasik(
        AnalisisKlasikRequest(
            sesi=sesi,
            bank_soal=bank_soal,
            pengerjaan=pengerjaan,
        )
    )

    # Reliabilitas KR-20 tinggi karena kedua soal konsisten
    assert hasil.reliabilitas.kr20 > 0.70

    s1 = next(b for b in hasil.butir_soal if b.soal_id == 1)
    s2 = next(b for b in hasil.butir_soal if b.soal_id == 2)

    # Tingkat kesukaran 2/4 = 0.5 (Sedang)
    assert s1.tingkat_kesukaran == 0.5
    assert s1.kategori_kesukaran == "Sedang (Ideal)"
    # Daya pembeda 1.0 - 0.0 = 1.0 (Sangat Baik)
    assert s1.daya_pembeda == 1.0
    assert s1.valid is True

    # Soal yang belum keluar (s2, jumlah_keluar=0) harus punya peluang keluar
    # berikutnya lebih besar daripada soal yang sudah keluar (s1, jumlah_keluar=4)
    assert s2.peluang_keluar_berikutnya > s1.peluang_keluar_berikutnya

    # Attempt analysis: percobaan 1 = 60, percobaan 2 = 85, selisih = +25
    assert hasil.ringkasan_percobaan.rata_nilai_percobaan_1 == 60.0
    assert hasil.ringkasan_percobaan.rata_nilai_percobaan_2 == 85.0
    assert hasil.ringkasan_percobaan.rata_peningkatan_1_ke_2 == 25.0
    assert hasil.ringkasan_percobaan.persen_siswa_meningkat == 100.0
