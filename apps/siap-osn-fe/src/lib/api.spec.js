import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api.js'
import { ambil, kosongkan } from '@/lib/cache.js'

describe('lib/api invalidasi cache', () => {
  const adapterAsli = api.defaults.adapter

  beforeEach(() => {
    kosongkan()
    api.defaults.adapter = vi.fn(async (config) => ({
      data: {},
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
    }))
  })
  afterEach(() => {
    api.defaults.adapter = adapterAsli
  })

  async function isiCache() {
    const fetcher = vi.fn().mockResolvedValue('nilai')
    await ambil('k', fetcher)
    return fetcher
  }

  it('mutasi sukses membuang cache GET', async () => {
    const fetcher = await isiCache()

    await api.put('/materi/1/progress', {})
    await ambil('k', fetcher)

    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('GET tidak membuang cache', async () => {
    const fetcher = await isiCache()

    await api.get('/materi')
    await ambil('k', fetcher)

    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('mutasi yang gagal tidak membuang cache', async () => {
    const fetcher = await isiCache()
    api.defaults.adapter = vi.fn(async (config) => {
      const err = new Error('gagal')
      err.config = config
      err.response = { status: 422, data: {}, config }
      throw err
    })

    await expect(api.post('/pretest', {})).rejects.toThrow('gagal')
    await ambil('k', fetcher)

    expect(fetcher).toHaveBeenCalledTimes(1)
  })
})
