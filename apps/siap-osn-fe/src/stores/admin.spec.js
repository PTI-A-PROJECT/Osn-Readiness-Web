import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { adminService } from '@/services/admin.js'
import { useAdminStore } from '@/stores/admin.js'

vi.mock('@/services/admin.js', () => ({
  adminService: { dashboard: vi.fn() },
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

const ringkasan = (siswaAktif, analitikPengguna = null) => ({
  siswaAktif,
  analitikPengguna,
  pengerjaanPerJenis: [],
  rataRataNilai: [],
  siswaPerTingkat: [],
})

function tunda() {
  let resolve
  const promise = new Promise((a) => {
    resolve = a
  })
  return { promise, resolve }
}

describe('adminStore · muatRingkasan', () => {
  it('respons filter lama yang datang belakangan tidak menimpa filter terbaru', async () => {
    const lama = tunda()
    const baru = tunda()
    adminService.dashboard.mockReturnValueOnce(lama.promise).mockReturnValueOnce(baru.promise)
    const store = useAdminStore()

    const p1 = store.muatRingkasan({ dari: '2026-01-01' })
    const p2 = store.muatRingkasan({ dari: '2026-02-01' })
    baru.resolve(ringkasan(2))
    await p2
    lama.resolve(ringkasan(1))
    await p1

    expect(store.ringkasan.siswaAktif).toBe(2)
    expect(store.ringkasanPeriode).toEqual({ dari: '2026-02-01', sampai: null })
    expect(store.ringkasanMemuat).toBe(false)
    expect(store.error).toBe(false)
  })

  it('gagal memuat periode mempertahankan angka seluruh waktu dan membuang analitik lama', async () => {
    adminService.dashboard.mockResolvedValueOnce(ringkasan(5, { siswaTerdaftar: 9 }))
    const store = useAdminStore()
    await store.muatRingkasan()

    adminService.dashboard.mockRejectedValueOnce(new Error('jaringan'))
    await expect(store.muatRingkasan({ dari: '2026-01-01' })).rejects.toThrow('jaringan')

    expect(store.ringkasan).toMatchObject({ siswaAktif: 5, analitikPengguna: null })
    expect(store.ringkasanPeriode).toBeNull()
    expect(store.error).toBe(true)
    expect(store.ringkasanMemuat).toBe(false)
  })

  it('gagal tanpa data sebelumnya tidak membuat ringkasan palsu', async () => {
    adminService.dashboard.mockRejectedValueOnce(new Error('jaringan'))
    const store = useAdminStore()

    await expect(store.muatRingkasan()).rejects.toThrow('jaringan')

    expect(store.ringkasan).toBeNull()
  })
})
