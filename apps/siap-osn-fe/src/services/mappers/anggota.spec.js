import { describe, expect, it } from 'vitest'
import { mapRingkasanAnggota } from '@/services/mappers/anggota.js'

describe('mapRingkasanAnggota', () => {
  it('memetakan empat field ringkasan', () => {
    expect(mapRingkasanAnggota({
      jumlah_referal: '3', peringkat: 2, jumlah_anggota: '32', total_referal: 41,
    })).toEqual({ jumlahReferal: 3, peringkat: 2, jumlahAnggota: 32, totalReferal: 41 })
  })

  it('mengganti field hilang atau bukan angka dengan nol', () => {
    expect(mapRingkasanAnggota({ jumlah_referal: 'abc', peringkat: null })).toEqual({
      jumlahReferal: 0, peringkat: 0, jumlahAnggota: 0, totalReferal: 0,
    })
    expect(mapRingkasanAnggota(undefined)).toEqual({
      jumlahReferal: 0, peringkat: 0, jumlahAnggota: 0, totalReferal: 0,
    })
  })
})
