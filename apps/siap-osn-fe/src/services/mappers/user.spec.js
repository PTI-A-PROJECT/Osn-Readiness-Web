import { describe, expect, it } from 'vitest'
import { mapRole, mapUser } from '@/services/mappers/user.js'

const userLaravel = {
  id: 1,
  name: 'Budi',
  email: 'budi@example.com',
  roles: ['siswa'],
  kode_referal: 'K7XW',
  jumlah_diundang: 3,
  created_at: '2026-09-25T00:00:00Z',
}

describe('mapRole', () => {
  it('Super Admin menjadi super_admin', () => {
    expect(mapRole(['Super Admin'])).toBe('super_admin')
  })

  it('siswa tetap siswa', () => {
    expect(mapRole(['siswa'])).toBe('siswa')
  })

  it('tak dikenal / kosong menjadi null', () => {
    expect(mapRole(['alien'])).toBeNull()
    expect(mapRole([])).toBeNull()
    expect(mapRole(null)).toBeNull()
  })
})

describe('mapUser', () => {
  it('memetakan UserResource ke bentuk FE', () => {
    expect(mapUser(userLaravel)).toEqual({
      id: 1,
      nama: 'Budi',
      email: 'budi@example.com',
      role: 'siswa',
      kodeReferal: 'K7XW',
      jumlahUndang: 3,
      created_at: '2026-09-25T00:00:00Z',
    })
  })

  it('kode dan jumlah undangan bawaan bila backend tidak mengirim', () => {
    expect(mapUser({ id: 2, name: 'Ayu', email: 'ayu@example.com' })).toEqual({
      id: 2,
      nama: 'Ayu',
      email: 'ayu@example.com',
      role: null,
      kodeReferal: null,
      jumlahUndang: 0,
      created_at: null,
    })
  })

  it('jumlah undangan bukan angka dianggap nol', () => {
    expect(mapUser({ id: 3, jumlah_diundang: undefined }).jumlahUndang).toBe(0)
    expect(mapUser({ id: 3, jumlah_diundang: '4' }).jumlahUndang).toBe(4)
  })

  it('null menjadi null', () => {
    expect(mapUser(null)).toBeNull()
  })
})
