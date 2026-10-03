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
  it('ping mengembalikan pesan pong', async () => {
    api.get.mockResolvedValue({ data: { message: 'OK', data: { message: 'pong' } } })
    await expect(adminService.ping()).resolves.toBe('pong')
    expect(api.get).toHaveBeenCalledWith('/admin/ping')
  })
})
