import { api } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'

async function ambilData(path, tingkat) {
  const response = await api.get(path, { params: { tingkat } })
  return response?.data?.data ?? {}
}

export const leaderboardService = {
  async daftar({ tingkat = 'kabupaten' } = {}) {
    return ambilData(ENDPOINTS.siswa.leaderboard, tingkat)
  },

  async posisiSaya({ tingkat = 'kabupaten' } = {}) {
    return ambilData(ENDPOINTS.siswa.leaderboardMe, tingkat)
  },
}
