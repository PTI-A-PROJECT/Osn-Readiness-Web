import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import KodeView from '@/views/anggota/KodeView.vue'
import { authService } from '@/services/auth.js'
import { anggotaService } from '@/services/anggota.js'
import { useAuthStore } from '@/stores/auth.js'

vi.mock('@/services/auth.js', () => ({
  authService: { tokenTersimpan: vi.fn(() => null), simpanToken: vi.fn(), me: vi.fn() },
}))
vi.mock('@/services/anggota.js', () => ({ anggotaService: { ubahKode: vi.fn() } }))

function render() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 3, nama: 'Ani', role: 'anggota', kodeReferal: 'OSNLAMA', jumlahUndang: 2 }
  const wrapper = mount(KodeView, { global: { plugins: [pinia] } })
  return { wrapper, auth }
}

beforeEach(() => vi.resetAllMocks())

describe('KodeView anggota', () => {
  it('memuat kode dan jumlah dari auth service', async () => {
    authService.me.mockResolvedValue({ kodeReferal: 'OSNACHMAD', jumlahUndang: 5 })
    const { wrapper } = render()
    await vi.waitFor(() => expect(wrapper.text()).toContain('5 pengguna sudah mendaftar lewat kode Anda'))
    expect(wrapper.get('#kode-referal').element.value).toBe('OSNACHMAD')
    expect(wrapper.get('#nama-kode').element.value).toBe('ACHMAD')
  })

  it('mempertahankan user store bila permintaan me gagal', async () => {
    authService.me.mockRejectedValue(new Error('offline'))
    const { wrapper, auth } = render()
    await vi.waitFor(() => expect(wrapper.get('#kode-referal').element.value).toBe('OSNLAMA'))
    expect(wrapper.get('#nama-kode').element.value).toBe('LAMA')
    expect(auth.user).not.toBeNull()
  })

  it('menyimpan nama kode dan memperbarui tampilan', async () => {
    authService.me.mockResolvedValue({ kodeReferal: 'OSNACHMAD', jumlahUndang: 5 })
    anggotaService.ubahKode.mockResolvedValue({ id: 3, nama: 'Ani', role: 'anggota', kodeReferal: 'OSNBEBAS', jumlahUndang: 5 })
    const { wrapper } = render()
    await vi.waitFor(() => expect(wrapper.get('#nama-kode').element.value).toBe('ACHMAD'))
    await wrapper.get('#nama-kode').setValue('BEBAS')
    await wrapper.findAll('button').find((button) => button.text().includes('Simpan nama')).trigger('click')
    await vi.waitFor(() => expect(wrapper.get('#kode-referal').element.value).toBe('OSNBEBAS'))
    expect(anggotaService.ubahKode).toHaveBeenCalledWith('BEBAS')
    expect(wrapper.get('[role="status"]').text()).toBe('Kode diperbarui.')
  })

  it('menampilkan galat validasi nama dari backend', async () => {
    authService.me.mockResolvedValue({ kodeReferal: 'OSNACHMAD', jumlahUndang: 5 })
    anggotaService.ubahKode.mockRejectedValue({ response: { status: 422, data: { errors: {
      nama: ['Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.'],
    } } } })
    const { wrapper } = render()
    await vi.waitFor(() => expect(wrapper.get('#nama-kode').element.value).toBe('ACHMAD'))
    await wrapper.get('#nama-kode').setValue('BEBAS')
    await wrapper.findAll('button').find((button) => button.text().includes('Simpan nama')).trigger('click')
    await vi.waitFor(() => expect(wrapper.get('[role="alert"]').text()).toBe('Nama hanya boleh huruf dan angka tanpa spasi, 2 sampai 16 karakter.'))
  })
})
