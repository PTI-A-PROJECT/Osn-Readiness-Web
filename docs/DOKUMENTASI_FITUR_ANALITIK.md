# Dokumentasi Fitur: Analisis Log Aktivitas & Evaluasi Mutu Soal (SIAP OSN)

Dokumentasi implementasi fitur evaluasi pembelajaran dan psikometri butir soal berdasarkan saran dosen pengampu mata kuliah Big Data.

---

## 1. Fitur Persentase Kali Keluar Soal (*Item Exposure Rate & Balancing*)

### Latar Belakang
Pada sistem sebelumnya, pemilihan soal untuk latihan, pretest, dan simulasi dilakukan secara acak murni (*random sampling*). Hal ini berisiko menimbulkan fenomena di mana beberapa soal muncul berulang kali kepada siswa, sedangkan sebagian besar soal lain di bank soal tidak pernah terpakai.

### Solusi & Logika Pemilihan Berbobot (*Inverse Exposure Weighting*)
1. **Audit Kemunculan:** Sistem mencatat frekuensi berapa kali setiap butir soal telah disajikan ke peserta ($f_i$) melalui akumulasi tabel riwayat (`pretest_jawaban`, `quiz_jawaban`, dan `hasil_simulasi_jawaban`).
2. **Formula Pembobotan Terbalik:** Setiap soal dalam pool kandidat diberi bobot peluang:
   $$W_i = \frac{1}{f_i + 1}$$
   * Soal belum pernah keluar ($f_i = 0$): $W_i = \frac{1}{0 + 1} = \mathbf{1.0}$ (Prioritas Utama).
   * Soal pernah keluar 1 kali ($f_i = 1$): $W_i = \frac{1}{1 + 1} = \mathbf{0.5}$ (Peluang berkurang 50%).
   * Soal pernah keluar 4 kali ($f_i = 4$): $W_i = \frac{1}{4 + 1} = \mathbf{0.2}$ (Peluang makin kecil).
3. **Peluang Keluar Berikutnya (%):** Dihitung dari rasio bobot soal terhadap total bobot pool pada level dan peruntukan yang bersangkutan:
   $$P(\text{keluar}) = \frac{W_i}{\sum_{k=1}^{N} W_k} \times 100\%$$

---

## 2. Fitur Analisis Percobaan (*Attempt Analysis*: Nyoba 1x vs 2x vs 3x+)

### Latar Belakang
Platform SIAP OSN menyediakan fasilitas mengulang latihan tanpa batas dan kuota simulasi hingga 3 kali. Fitur ini dirancang untuk membuktikan efektivitas materi dan pembahasan dalam mendongkrak kurva belajar (*learning curve*) siswa.

### Metrik yang Dihasilkan
* **Nilai Percobaan 1 ($A_1$):** Skor rata-rata siswa saat pertama kali mengerjakan paket tes.
* **Nilai Percobaan 2 ($A_2$):** Skor rata-rata siswa saat mencoba untuk kedua kalinya.
* **Nilai Percobaan 3+ ($A_{3+}$):** Skor rata-rata siswa pada pengerjaan ketiga atau lebih.
* **Delta Peningkatan ($\Delta$):** Selisih kenaikan nilai ($\Delta = A_2 - A_1$).
* **Rasio Siswa Meningkat (%):** Persentase siswa yang skornya mengalami kenaikan pada percobaan ulang.

---

## 3. Fitur 4 Tes Klasik Butir Soal (*Classical Test Theory / CTT*)

Layanan analitik mengevaluasi kelayakan butir soal OSN menggunakan 4 parameter psikometri standar:

| Parameter | Formula / Metode | Rentang & Interpretasi |
|---|---|---|
| **1. Tingkat Kesukaran ($P$)** | $P = \frac{\text{Jumlah Siswa Benar}}{\text{Total Penjawab}}$ | • $P > 0.70$: Mudah<br>• $0.30 \le P \le 0.70$: **Sedang (Ideal)**<br>• $P < 0.30$: Sukar |
| **2. Daya Pembeda ($D$)** | $D = P_{\text{atas (27\%)}} - P_{\text{bawah (27\%)}}$ | • $D \ge 0.30$: **Baik (Membedakan kemampuan)**<br>• $0.20 \le D < 0.30$: Cukup / Perlu Revisi<br>• $D < 0.00$: **Cacat (Kunci jawaban tertukar/ambigu)** |
| **3. Validitas Butir ($r_{pbis}$)** | Korelasi *Point-Biserial* antara skor butir (0/1) dengan total skor tes. | • $r_{pbis} \ge 0.20$: **Valid**<br>• $r_{pbis} < 0.20$: Kurang / Tidak Valid |
| **4. Reliabilitas Tes ($\text{KR-20}$)** | Formula *Kuder-Richardson 20* untuk instrumen dikotomi: $r_{11} = \frac{k}{k-1} \left(1 - \frac{\sum pq}{S^2}\right)$ | • $\text{KR-20} \ge 0.70$: **Paket Tes Reliabel (Konsisten)**<br>• $\text{KR-20} < 0.70$: Kurang Reliabel |

---

## 4. Daftar File yang Diubah & Dibuat

### A. Service Analytics Python (`apps/data-analytics`)
* `src/data_analytics/klasik.py` *(Baru)*: Modul kalkulasi CTT, exposure rate, bobot inverse, dan attempt grouping.
* `src/data_analytics/schemas.py`: Penambahan DTO request/response untuk analisis klasik dan exposure.
* `src/data_analytics/api.py`: Penambahan endpoint stateless `POST /hitung/analisis-klasik`.
* `tests/test_klasik.py` *(Baru)*: Pengujian unit test rumus statistika.

### B. Backend Laravel (`apps/Osn-Readiness-Web`)
* `app/Repositories/Eloquent/SoalRepository.php`: Penambahan subquery `jumlah_keluar` pada query kandidat soal.
* `app/Services/SoalPickerService.php`: Integrasi fungsi `acakBerbobotKemunculan()`.
* `app/Contracts/Clients/PerhitunganClientInterface.php` & `app/Clients/PerhitunganClient.php`: Integrasi HTTP client ke endpoint analitik Python.
* `app/Contracts/Services/AktivitasServiceInterface.php` & `app/Services/AktivitasService.php`: Pengumpulan data sesi, bank soal, dan riwayat attempt.
* `app/Http/Controllers/Api/Admin/AktivitasController.php`: Endpoint controller `GET /api/admin/aktivitas/analisis`.
* `routes/api.php`: Pendaftaran rute API admin `aktivitas/analisis`.
* `database/seeders/AnalisisContohSeeder.php` *(Baru)*: Data sampel pengerjaan siswa untuk uji coba lokal.
* `tests/Unit/Services/SoalPickerServiceTest.php`: Unit test validasi algoritma pembobotan soal (20/20 PASS).

### C. Frontend Vue 3 (`apps/siap-osn-fe`)
* `src/lib/endpoints.js` & `src/services/admin.js`: Registrasi API `aktivitasAnalisis`.
* `src/layouts/AppLayout.vue`: Penyesuaian navigasi menu sidebar AUDIT.
* `src/views/admin/AktivitasView.vue`: Desain antarmuka 4 tab interaktif (Log Aktivitas, Analisis Percobaan, Persentase Soal Keluar, dan 4 Tes Klasik).

---

## 5. Cara Pengujian & Verifikasi

1. **Akses Antarmuka Web:**
   * Login sebagai Super Admin (`admin@example.com` / `password`).
   * Masuk ke menu sidebar **AUDIT** $\rightarrow$ **Log Aktivitas** (`/admin/aktivitas`).
   * Klik tab:
     * **Analisis Percobaan (1x vs 2x)** untuk melihat kurva belajar siswa.
     * **Persentase Kali Keluar Soal** untuk melihat perbandingan kali keluar dan bobot peluang berikutnya.
     * **4 Tes Klasik Soal (CTT)** untuk melihat metrik psikometri mutu soal.

2. **Verifikasi Otomatis (Unit Test):**
   * Backend Laravel: `php artisan test --filter=SoalPickerServiceTest` $\rightarrow$ **PASS (20 tests)**.
   * Frontend Vitest: `bun run test:unit` $\rightarrow$ **PASS (34 test files, 257 tests)**.
