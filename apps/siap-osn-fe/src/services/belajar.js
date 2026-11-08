import { api } from '@/lib/api.js'
import { ambil } from '@/lib/cache.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapMateri, mapMateriDetail, mapProgressTersimpan, mapProgressSubmateriTersimpan } from '@/services/mappers/belajar.js'
import { unwrap } from '@/services/envelope.js'

// Satu fungsi = satu endpoint. Daftar/detail me-return Resource langsung
// ({data} tanpa message) — unwrap tetap bisa dipakai karena yang diambil
// hanya `data`.
export const belajarService = {
  // GET /materi?tingkat_id= -> daftar terurut server (wajib dulu).
  async daftar({ tingkatId = null } = {}) {
    const params = {}
    if (tingkatId) params.tingkat_id = tingkatId
    const res = await ambil(`materi:${tingkatId ?? ''}`, () => api.get(ENDPOINTS.siswa.materi, { params }), { ttl: 5 * 60_000 })
    const list = unwrap(res)
    return (Array.isArray(list) ? list : []).map(mapMateri)
  },

  // GET /materi/{id} -> detail + isi/file/gambar.
  async detail({ id } = {}) {
    const res = await ambil(`materi-detail:${id}`, () => api.get(ENDPOINTS.siswa.materiDetail(id)), { ttl: 2 * 60_000 })
    return mapMateriDetail(unwrap(res))
  },

  // PUT /materi/{id}/progress {status: 'selesai'}.
  async tandaiSelesai({ id, signal } = {}) {
    return mapProgressTersimpan(
      unwrap(await api.put(ENDPOINTS.siswa.materiProgress(id), { status: 'selesai' }, { signal })),
    )
  },

  // PUT /materi/{id}/submateri/{kode}/selesai.
  async tandaiSubmateriSelesai({ id, submateriId, signal } = {}) {
    return mapProgressSubmateriTersimpan(
      unwrap(await api.put(ENDPOINTS.siswa.materiSubmateriSelesai(id, submateriId), {}, { signal })),
    )
  },
}
