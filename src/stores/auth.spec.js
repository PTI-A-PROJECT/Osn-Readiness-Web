import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api, TOKEN_KEY } from '@/lib/api.js'
import { mapUser, useAuthStore } from '@/stores/auth.js'

vi.mock('@/lib/api', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

// Bentuk UserResource dari backend Laravel.
const userLaravel = {
  id: 1,
  name: 'Budi',
  email: 'budi@example.com',
  is_active: true,
  tingkat_aktif_id: null,
  roles: ['siswa'],
  created_at: '2026-09-25T00:00:00Z',
  updated_at: '2026-09-25T00:00:00Z',
}

const userSiswa = {
  id: 1,
  nama: 'Budi',
  email: 'budi@example.com',
  role: 'siswa',
  created_at: '2026-09-25T00:00:00Z',
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  localStorage.clear()
})

describe('mapUser', () => {
  it('memetakan UserResource siswa ke bentuk FE', () => {
    expect(mapUser(userLaravel)).toEqual(userSiswa)
  })

  it('memetakan role Super Admin ke super_admin', () => {
    expect(mapUser({ ...userLaravel, roles: ['Super Admin'] }).role).toBe('super_admin')
  })

  it('role tak dikenal menjadi null', () => {
    expect(mapUser({ ...userLaravel, roles: ['alien'] }).role).toBeNull()
  })
})

describe('auth store', () => {
  it('fetchMe sukses mengisi user dan initialized', async () => {
    localStorage.setItem(TOKEN_KEY, 'tok123')
    api.get.mockResolvedValue({ data: { message: 'OK', data: userLaravel } })
    const auth = useAuthStore()
    await auth.fetchMe()
    expect(api.get).toHaveBeenCalledWith('/auth/me')
    expect(auth.user).toEqual(userSiswa)
    expect(auth.initialized).toBe(true)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isSiswa).toBe(true)
    expect(auth.isSuperAdmin).toBe(false)
  })

  it('fetchMe tanpa token tidak memanggil API', async () => {
    const auth = useAuthStore()
    await auth.fetchMe()
    expect(api.get).not.toHaveBeenCalled()
    expect(auth.user).toBeNull()
    expect(auth.initialized).toBe(true)
  })

  it('fetchMe 401 mengosongkan user dan token', async () => {
    localStorage.setItem(TOKEN_KEY, 'tok-basi')
    api.get.mockRejectedValue({ response: { status: 401 } })
    const auth = useAuthStore()
    await auth.fetchMe()
    expect(auth.user).toBeNull()
    expect(auth.initialized).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem(TOKEN_KEY)).toBeNull()
  })

  it('login menyimpan token dan user terpeta', async () => {
    api.post.mockResolvedValue({ data: { message: 'Login berhasil', data: { user: userLaravel, token: 'tok123' } } })
    const auth = useAuthStore()
    const user = await auth.login({ email: 'budi@example.com', password: 'password123' })
    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'budi@example.com',
      password: 'password123',
    })
    expect(user).toEqual(userSiswa)
    expect(auth.isAuthenticated).toBe(true)
    expect(localStorage.getItem(TOKEN_KEY)).toBe('tok123')
  })

  it('register mengirim password_confirmation tanpa login', async () => {
    api.post.mockResolvedValue({ data: { message: 'Registrasi berhasil', data: { user: userLaravel } } })
    const auth = useAuthStore()
    const result = await auth.register({ nama: 'Budi', email: 'budi@example.com', password: 'password123', konfirmasi: 'password123' })
    expect(api.post).toHaveBeenCalledWith('/auth/register', {
      name: 'Budi',
      email: 'budi@example.com',
      password: 'password123',
      password_confirmation: 'password123',
    })
    expect(result).toEqual(userSiswa)
    expect(auth.user).toBeNull()
  })

  it('logout mengosongkan user dan token meski request gagal', async () => {
    localStorage.setItem(TOKEN_KEY, 'tok123')
    api.post.mockRejectedValue(new Error('network'))
    const auth = useAuthStore()
    auth.user = userSiswa
    await auth.logout()
    expect(auth.user).toBeNull()
    expect(localStorage.getItem(TOKEN_KEY)).toBeNull()
  })
})
