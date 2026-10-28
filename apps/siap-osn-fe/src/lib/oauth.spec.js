import { describe, expect, it, vi } from 'vitest'
import { PESAN_GOOGLE_GAGAL, pesanExchangeGagal, urlRedirectGoogle } from '@/lib/oauth.js'

describe('lib/oauth', () => {
  it('urlRedirectGoogle memakai origin API tanpa /api', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'https://api.mrcln0305.xyz/api')
    expect(urlRedirectGoogle()).toBe('https://api.mrcln0305.xyz/auth/google/redirect')

    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8080/api/')
    expect(urlRedirectGoogle()).toBe('http://localhost:8080/auth/google/redirect')

    vi.unstubAllEnvs()
  })

  it('setiap kode kegagalan backend punya pesan (peta lengkap)', () => {
    const kodeBackend = [
      'google_batal',
      'google_state',
      'google_gagal',
      'google_taut',
      'google_admin',
      'google_nonaktif',
      'google_email_verifikasi',
    ]
    for (const kode of kodeBackend) {
      expect(PESAN_GOOGLE_GAGAL[kode]).toBeTypeOf('string')
      expect(PESAN_GOOGLE_GAGAL[kode].length).toBeGreaterThan(0)
    }
  })

  it('kode error exchange backend (UPPER_SNAKE) diterjemahkan ke pesan spesifik', () => {
    expect(pesanExchangeGagal('KODE_GOOGLE_TIDAK_VALID')).toBe(PESAN_GOOGLE_GAGAL.google_exchange)
    expect(pesanExchangeGagal('GOOGLE_AKUN_ADMIN')).toBe(PESAN_GOOGLE_GAGAL.google_admin)
    expect(pesanExchangeGagal('AKUN_TIDAK_AKTIF')).toBe(PESAN_GOOGLE_GAGAL.google_nonaktif)
  })

  it('kode exchange tak dikenal atau kosong jatuh ke pesan umum', () => {
    expect(pesanExchangeGagal(undefined)).toBe(PESAN_GOOGLE_GAGAL.lain)
    expect(pesanExchangeGagal('KODE_LAIN')).toBe(PESAN_GOOGLE_GAGAL.lain)
  })
})
