import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api.js'
import { adminService } from '@/services/admin.js'

vi.mock('@/lib/api.js', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

beforeEach(() => {
  vi.resetAllMocks()
})

describe('adminService', () => {
  it('dashboard mengambil ringkasan dari endpoint yang ada', async () => {
    api.get.mockResolvedValue({ data: { message: 'OK', data: { siswa_aktif: 12 } } })
    await expect(adminService.dashboard()).resolves.toEqual({ siswa_aktif: 12 })
    expect(api.get).toHaveBeenCalledWith('/admin/dashboard')
  })
})
