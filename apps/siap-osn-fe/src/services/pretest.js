import { api } from '@/lib/api.js'
import { ambil } from '@/lib/cache.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapHasilPretest, mapTingkat, paketPretest } from '@/services/mappers/pretest.js'
import { unwrap } from '@/services/envelope.js'

// Satu fungsi = satu endpoint. Masuk & keluar bentuk FE;
// konversi Laravel di services/mappers/pretest.js.
export const pretestService = {
  // GET /tingkat -> daftar level + tahap (untuk pemilih tingkat manual).
  async tingkat() {
    const res = await ambil('tingkat', () => api.get(ENDPOINTS.siswa.tingkat), { ttl: 5 * 60_000 })
    const list = unwrap(res)
    return (Array.isArray(list) ? list : []).map(mapTingkat)
  },

  // POST /pretest {tingkat_id} -> 201 (baru) atau 200 (resume yang berjalan).
  async mulai({ tingkatId, signal } = {}) {
    const paket = paketPretest(unwrap(await api.post(ENDPOINTS.siswa.pretestMulai, { tingkat_id: tingkatId }, { signal })))
    // 'menunggu' tidak mungkin dari mulai (belum ada submit), tapi jangan dilempar.
if (paket.jenis === 'hasil') throw new Error('Respons mulai pre-test tidak berisi soal')
    return paket
  },

  // GET /pretest/{id} -> paket pengerjaan (resume) atau paket hasil.
  async lihat({ id } = {}) {
    // Hanya hasil final yang di-cache: pengerjaan/menunggu harus selalu segar.
    const res = await ambil(`pretest:${id}`, () => api.get(ENDPOINTS.siswa.pretest(id)), {
      ttl: 0,
      permanen: (r) => paketPretest(unwrap(r)).jenis === 'hasil',
    })
    return paketPretest(unwrap(res))
  },

  // PUT /pretest/{id}/jawaban {soal_id, jawaban_user} -> { message } tanpa data.
  // jawaban null = kosongkan jawaban soal itu.
  async simpanJawaban({ id, soalId, jawaban, signal } = {}) {
    await api.put(
      ENDPOINTS.siswa.pretestJawaban(id),
      { soal_id: soalId, jawaban_user: jawaban ?? null },
      { signal },
    )
  },

  // POST /pretest/{id}/submit (body kosong, idempoten) -> hasil.
  async kumpulkan({ id, signal } = {}) {
    return mapHasilPretest(unwrap(await api.post(ENDPOINTS.siswa.pretestSubmit(id), {}, { signal })))
  },
}
