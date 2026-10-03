import { describe, expect, it } from 'vitest'
import { pesanError, pesanField } from '@/lib/errors.js'

describe('pesanField', () => {
  it('422 Laravel email ganda diterjemahkan', () => {
    const err = { response: { status: 422, data: { errors: { email: ['The email has already been taken.'] } } } }
    expect(pesanField(err, 'email')).toBe('Email sudah terdaftar')
  })

  it('400 Go lama tetap dibaca', () => {
    const err = { response: { status: 400, data: { message: 'Email sudah terdaftar' } } }
    expect(pesanField(err, 'email')).toBe('Email sudah terdaftar')
  })

  it('bukan error validasi menjadi null', () => {
    expect(pesanField({ response: { status: 500, data: {} } }, 'email')).toBeNull()
    expect(pesanField({}, 'email')).toBeNull()
  })
})

describe('pesanError', () => {
  it('fallback bila tak ada pesan', () => {
    expect(pesanError({})).toBe('Terjadi kesalahan, coba lagi')
  })

  it('pesan backend diteruskan', () => {
    const err = { response: { status: 401, data: { message: 'Email atau password salah' } } }
    expect(pesanError(err)).toBe('Email atau password salah')
  })

  it('offline dan timeout memakai pesan Indonesia', () => {
    expect(pesanError({ isAxiosError: true, code: 'ERR_NETWORK' })).toMatch(/Tidak dapat terhubung/)
    expect(pesanError({ isAxiosError: true, code: 'ECONNABORTED' })).toMatch(/terlalu lama/)
  })

  it('5xx tidak menampilkan pesan Inggris dari Laravel', () => {
    const err = { response: { status: 500, data: { message: 'Server Error' } } }
    expect(pesanError(err, 'Gagal menyimpan')).toBe('Gagal menyimpan')
  })
})
