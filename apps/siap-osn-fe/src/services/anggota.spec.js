import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api.js'
import { anggotaService } from '@/services/anggota.js'

vi.mock('@/lib/api.js', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), put: vi.fn() },
}))

beforeEach(() => vi.resetAllMocks())

describe('anggotaService', () => {
  it('mengambil dan memetakan ringkasan anggota', async () => {
    api.get.mockResolvedValue({ data: { message: 'OK', data: {
      jumlah_referal: 3, peringkat: 2, jumlah_anggota: 32, total_referal: 41,
    } } })
    await expect(anggotaService.ringkasan()).resolves.toEqual({
      jumlahReferal: 3, peringkat: 2, jumlahAnggota: 32, totalReferal: 41,
    })
    expect(api.get).toHaveBeenCalledWith('/anggota/ringkasan', { signal: undefined })
  })

  it('mengubah nama kode dan mengembalikan user terpetakan', async () => {
    api.put.mockResolvedValue({ data: { message: 'OK', data: {
      id: 7, name: 'Budi', roles: ['anggota'], kode_referal: 'OSNBUDI', jumlah_diundang: 5,
    } } })
    await expect(anggotaService.ubahKode('BUDI')).resolves.toEqual({
      id: 7, nama: 'Budi', email: '', role: 'anggota', kodeReferal: 'OSNBUDI', jumlahUndang: 5, created_at: null,
    })
    expect(api.put).toHaveBeenCalledWith('/anggota/kode-referal', { nama: 'BUDI' })
  })
})
