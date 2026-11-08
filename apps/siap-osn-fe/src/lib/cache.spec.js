import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ambil, invalidasi, kosongkan } from '@/lib/cache.js'

describe('lib/cache', () => {
  beforeEach(() => {
    kosongkan()
    vi.useFakeTimers()
  })
  afterEach(() => vi.useRealTimers())

  it('memakai hasil cache selama TTL lalu mengambil ulang', async () => {
    const fetcher = vi.fn().mockResolvedValueOnce('a').mockResolvedValueOnce('b')

    expect(await ambil('k', fetcher, { ttl: 1000 })).toBe('a')
    expect(await ambil('k', fetcher, { ttl: 1000 })).toBe('a')
    expect(fetcher).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(1001)
    expect(await ambil('k', fetcher, { ttl: 1000 })).toBe('b')
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('menyatukan request yang sedang berjalan', async () => {
    let selesai
    const fetcher = vi.fn(() => new Promise((r) => { selesai = r }))

    const p1 = ambil('k', fetcher)
    const p2 = ambil('k', fetcher)
    selesai('x')

    expect(await Promise.all([p1, p2])).toEqual(['x', 'x'])
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('tidak menyimpan kegagalan', async () => {
    const fetcher = vi.fn().mockRejectedValueOnce(new Error('gagal')).mockResolvedValueOnce('ok')

    await expect(ambil('k', fetcher)).rejects.toThrow('gagal')
    expect(await ambil('k', fetcher)).toBe('ok')
  })

  it('invalidasi membuang entri biasa tetapi mempertahankan hasil final', async () => {
    const biasa = vi.fn().mockResolvedValue('biasa')
    const final = vi.fn().mockResolvedValue({ jenis: 'hasil' })
    const adalahHasil = (v) => v.jenis === 'hasil'

    await ambil('biasa', biasa)
    await ambil('final', final, { permanen: adalahHasil })
    invalidasi()
    await ambil('biasa', biasa)
    await ambil('final', final, { permanen: adalahHasil })

    expect(biasa).toHaveBeenCalledTimes(2)
    expect(final).toHaveBeenCalledTimes(1)
  })

  it('tidak menyimpan hasil yang bukan final walau permanen diminta', async () => {
    const fetcher = vi.fn().mockResolvedValue({ jenis: 'pengerjaan' })
    const opsi = { ttl: 0, permanen: (v) => v.jenis === 'hasil' }

    await ambil('k', fetcher, opsi)
    await ambil('k', fetcher, opsi)

    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('mutasi saat request berjalan membuat hasil lamanya tidak disimpan', async () => {
    let selesai
    const fetcher = vi.fn()
      .mockImplementationOnce(() => new Promise((r) => { selesai = r }))
      .mockResolvedValueOnce('baru')

    const lama = ambil('k', fetcher)
    invalidasi()
    selesai('basi')
    expect(await lama).toBe('basi')

    expect(await ambil('k', fetcher)).toBe('baru')
  })

  it('kosongkan membuang semuanya termasuk hasil final', async () => {
    const fetcher = vi.fn().mockResolvedValue({ jenis: 'hasil' })
    const opsi = { permanen: (v) => v.jenis === 'hasil' }

    await ambil('k', fetcher, opsi)
    kosongkan()
    await ambil('k', fetcher, opsi)

    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('force melewati cache', async () => {
    const fetcher = vi.fn().mockResolvedValueOnce('a').mockResolvedValueOnce('b')

    await ambil('k', fetcher)
    expect(await ambil('k', fetcher, { force: true })).toBe('b')
  })
})
