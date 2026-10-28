import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api.js'
import { authService } from '@/services/auth.js'

// Service diuji dengan lib/api di-mock (lihat §1.3.5 + §5.4).
vi.mock('@/lib/api.js', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

const userLaravel = {
  id: 1,
  name: 'Budi',
  email: 'budi@example.com',
  roles: ['siswa'],
  created_at: '2026-09-25T00:00:00Z',
}

beforeEach(() => {
  vi.resetAllMocks()
  localStorage.clear()
})

describe('authService', () => {
  it('me mengembalikan user bentuk FE', async () => {
    api.get.mockResolvedValue({ data: { message: 'OK', data: userLaravel } })
    const user = await authService.me()
    expect(api.get).toHaveBeenCalledWith('/auth/me')
    expect(user).toEqual({
      id: 1,
      nama: 'Budi',
      email: 'budi@example.com',
      role: 'siswa',
      created_at: '2026-09-25T00:00:00Z',
      kodeReferal: null,
      jumlahUndang: 0,
    })
  })

  it('login mengembalikan user + token', async () => {
    api.post.mockResolvedValue({
      data: { message: 'Login berhasil', data: { user: userLaravel, token: 'tok123' } },
    })
    const res = await authService.login({ email: 'budi@example.com', password: 'password123' })
    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'budi@example.com',
      password: 'password123',
    })
    expect(res).toEqual({ user: expect.objectContaining({ nama: 'Budi' }), token: 'tok123' })
  })

  it('register memetakan ke field Laravel', async () => {
    api.post.mockResolvedValue({
      data: { message: 'Registrasi berhasil', data: { user: userLaravel, token: 'tok-register' } },
    })
    const res = await authService.register({
      nama: 'Budi',
      email: 'budi@example.com',
      password: 'password123',
      konfirmasi: 'password123',
    })
    expect(api.post).toHaveBeenCalledWith('/auth/register', {
      name: 'Budi',
      email: 'budi@example.com',
      password: 'password123',
      password_confirmation: 'password123',
    })
    expect(res).toEqual({ user: expect.objectContaining({ nama: 'Budi' }), token: 'tok-register' })
  })

  it('updateProfile mengirim name/email', async () => {
    api.put.mockResolvedValue({ data: { message: 'OK', data: userLaravel } })
    await authService.updateProfile({ nama: 'Budi', email: 'budi@example.com' })
    expect(api.put).toHaveBeenCalledWith('/auth/profile', {
      name: 'Budi',
      email: 'budi@example.com',
    })
  })

  it('tokenTersimpan/simpanToken baca-tulis localStorage', () => {
    expect(authService.tokenTersimpan()).toBeNull()
    authService.simpanToken('tok123')
    expect(authService.tokenTersimpan()).toBe('tok123')
    authService.simpanToken(null)
    expect(authService.tokenTersimpan()).toBeNull()
  })
})

describe('authService.exchangeGoogle', () => {
  it('menukar kode dan menandai akun baru yang perlu kode referal', async () => {
    api.post.mockResolvedValue({
      data: {
        message: 'Login berhasil',
        data: { user: userLaravel, token: 'tok-google', perlu_kode_referal: true },
      },
    })
    const res = await authService.exchangeGoogle('kode-sekali-pakai')
    expect(api.post).toHaveBeenCalledWith('/auth/google/exchange', { kode: 'kode-sekali-pakai' })
    expect(res).toEqual({
      user: expect.objectContaining({ nama: 'Budi' }),
      token: 'tok-google',
      perluKodeReferal: true,
    })
  })

  it('akun yang sudah ada tidak ditandai perlu kode referal', async () => {
    api.post.mockResolvedValue({
      data: {
        message: 'Login berhasil',
        data: { user: userLaravel, token: 'tok-google', perlu_kode_referal: false },
      },
    })
    const res = await authService.exchangeGoogle('kode')
    expect(res.perluKodeReferal).toBe(false)
  })
})

describe('authService.simpanReferal', () => {
  it('mengirim kode yang sudah dirapikan ke endpoint referal', async () => {
    api.post.mockResolvedValue({ data: { message: 'ok', data: userLaravel } })
    await authService.simpanReferal('  k7xw ')
    expect(api.post).toHaveBeenCalledWith('/auth/referal', { kode_referal: 'k7xw' })
  })

  it('mengirim null ketika dilewati', async () => {
    api.post.mockResolvedValue({ data: { message: 'ok', data: userLaravel } })
    await authService.simpanReferal('   ')
    expect(api.post).toHaveBeenCalledWith('/auth/referal', { kode_referal: null })
  })
})
