/*
 * Mapper belajar/materi. Kontrak backend (terverifikasi 2026-10-05):
 * - GET /materi?tingkat_id= -> { data: [baris] } (Resource langsung,
 *   TANPA envelope {message}; sudah terurut wajib+prioritas di server)
 * - Baris: { id, tingkat_id, kompetensi_id, urutan, judul, deskripsi,
 *     wajib, prioritas, progress: null|{status, persentase,
 *     tanggal_selesai}, nilai_latihan_terbaik, latihan_belum_tersedia }
 * - GET /materi/{id} -> baris + { isi_materi, file_materi, gambar }
 *   (nama file mentah, bukan URL — URL dibangun di sini)
 * - PUT /materi/{id}/progress {status} -> { message, data: { materi_id,
 *     status, persentase, tanggal_selesai } }
 */

const basisApi = () => String(import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/api\/?$/, '')
export const urlStorage = (nama) => (nama ? `${basisApi()}/storage/${nama}` : null)

export function mapProgress(p) {
  if (!p) return null
  return {
    status: p.status ?? null,
    persentase: p.persentase == null ? 0 : Number(p.persentase),
    tanggalSelesai: p.tanggal_selesai ?? null,
  }
}

export function mapMateri(r) {
  return {
    id: r.id,
    tingkatId: r.tingkat_id ?? null,
    kompetensiId: r.kompetensi_id ?? null,
    urutan: r.urutan ?? 0,
    judul: r.judul ?? '',
    deskripsi: r.deskripsi ?? '',
    wajib: Boolean(r.wajib),
    prioritas: r.prioritas ?? null,
    progress: mapProgress(r.progress),
    nilaiTerbaik: r.nilai_latihan_terbaik == null ? null : Number(r.nilai_latihan_terbaik),
    latihanTersedia: !r.latihan_belum_tersedia,
    quizId: r.quiz_id ?? null,
    quizNama: r.quiz_nama ?? null,
    quizDeskripsi: r.quiz_deskripsi ?? null,
    jumlahSoalLatihan: r.quiz_jumlah_soal == null ? null : Number(r.quiz_jumlah_soal),
  }
}

export function mapSubmateri(r) {
  return {
    id: r.id,
    judul: r.judul ?? '',
    konten: r.konten ?? '',
    selesai: Boolean(r.selesai),
    tanggalSelesai: r.tanggal_selesai ?? null,
  }
}

export function mapMateriDetail(r) {
  return {
    ...mapMateri(r),
    isi: r.isi_materi ?? '',
    fileUrl: urlStorage(r.file_materi),
    gambarUrl: urlStorage(r.gambar),
    submateri: Array.isArray(r.submateri) ? r.submateri.map(mapSubmateri) : [],
  }
}

export function mapProgressTersimpan(r) {
  return {
    materiId: r.materi_id,
    status: r.status ?? null,
    persentase: r.persentase == null ? 0 : Number(r.persentase),
    tanggalSelesai: r.tanggal_selesai ?? null,
  }
}

export function mapProgressSubmateriTersimpan(r) {
  return {
    ...mapProgressTersimpan(r),
    submateriId: r.submateri_id ?? null,
    submateriSelesai: Number(r.submateri_selesai ?? 0),
    totalSubmateri: Number(r.total_submateri ?? 0),
    submateri: Array.isArray(r.submateri) ? r.submateri.map(mapSubmateri) : [],
  }
}
