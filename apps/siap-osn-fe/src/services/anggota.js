import { api } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapUser } from '@/services/mappers/user.js'
import { mapRingkasanAnggota } from '@/services/mappers/anggota.js'
import { unwrap } from '@/services/envelope.js'

export const anggotaService = {
  // GET /anggota/ringkasan -> { message, data: ringkasan }
  async ringkasan({ signal } = {}) {
    return mapRingkasanAnggota(unwrap(await api.get(ENDPOINTS.anggota.ringkasan, { signal })))
  },
  // PUT /anggota/kode-referal { nama } -> { message, data: user }
  async ubahKode(nama) {
    return mapUser(unwrap(await api.put(ENDPOINTS.anggota.kodeReferal, { nama })))
  },
}
