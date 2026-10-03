import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api.js'
import { siswaService } from '@/services/siswa.js'

vi.mock('@/lib/api.js', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

beforeEach(() => {
  vi.resetAllMocks()
})

describe('siswaService', () => {
  it('dashboard memetakan respons ke bentuk UI', async () => {
    api.get.mockResolvedValue({
      data: {
        message: 'OK',
        data: { pre_test_selesai: true, materi_selesai: 3, materi_total: 6 },
      },
    })
    const hasil = await siswaService.dashboard({})
    expect(api.get).toHaveBeenCalledWith('/siswa/dashboard', { signal: undefined })
    expect(hasil.preTestSelesai).toBe(true)
    expect(hasil.materiSelesai).toBe(3)
    expect(hasil.materiTotal).toBe(6)
  })

  it('dashboard kosong tetap aman dipakai UI', async () => {
    api.get.mockResolvedValue({ data: { message: 'OK', data: null } })
    const hasil = await siswaService.dashboard({})
    expect(hasil.preTestSelesai).toBe(false)
    expect(hasil.kompetensi).toEqual([])
  })
})
