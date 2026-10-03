import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { siswaService } from '@/services/siswa.js'
import { useProgressStore } from '@/stores/progress.js'

vi.mock('@/services/siswa.js', () => ({
  siswaService: { dashboard: vi.fn() },
}))

const hasil = { preTestSelesai: false, materiSelesai: 0, materiTotal: 0 }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('progress store', () => {
  it('fetchDashboard menyimpan hasil dan menandai loaded', async () => {
    siswaService.dashboard.mockResolvedValue(hasil)
    const progress = useProgressStore()
    await progress.fetchDashboard()
    expect(progress.data).toEqual(hasil)
    expect(progress.loaded).toBe(true)
    expect(progress.loading).toBe(false)
  })

  it('data segar tidak memicu request ulang kecuali force', async () => {
    siswaService.dashboard.mockResolvedValue(hasil)
    const progress = useProgressStore()
    await progress.fetchDashboard()
    await progress.fetchDashboard()
    expect(siswaService.dashboard).toHaveBeenCalledTimes(1)
    await progress.fetchDashboard({ force: true })
    expect(siswaService.dashboard).toHaveBeenCalledTimes(2)
  })

  it('gagal request menandai error tanpa menghapus data lama', async () => {
    siswaService.dashboard.mockRejectedValue(new Error('putus'))
    const progress = useProgressStore()
    await progress.fetchDashboard()
    expect(progress.error).toBe(true)
    expect(progress.loaded).toBe(false)
  })

  it('$reset mengosongkan state', async () => {
    siswaService.dashboard.mockResolvedValue(hasil)
    const progress = useProgressStore()
    await progress.fetchDashboard()
    progress.$reset()
    expect(progress.loaded).toBe(false)
    expect(progress.error).toBe(false)
  })
})
