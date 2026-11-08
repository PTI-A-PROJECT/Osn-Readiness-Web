import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import DashboardView from '@/views/anggota/DashboardView.vue'
import { anggotaService } from '@/services/anggota.js'
import { useAuthStore } from '@/stores/auth.js'

vi.mock('@/services/anggota.js', () => ({ anggotaService: { ringkasan: vi.fn() } }))

function render() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 3, nama: 'Ani', role: 'anggota' }
  return mount(DashboardView, { global: { plugins: [pinia] } })
}

beforeEach(() => vi.resetAllMocks())

describe('DashboardView anggota', () => {
  it('menampilkan ringkasan referal dan peringkat', async () => {
    anggotaService.ringkasan.mockResolvedValue({ jumlahReferal: 3, peringkat: 2, jumlahAnggota: 32, totalReferal: 41 })
    const wrapper = render()
    await vi.waitFor(() => expect(wrapper.text()).toContain('2 dari 32'))
    expect(wrapper.text()).toContain('Halo, Ani.')
    expect(wrapper.text()).toContain('41')
  })

  it('menyembunyikan peringkat saat belum ada referal', async () => {
    anggotaService.ringkasan.mockResolvedValue({ jumlahReferal: 0, peringkat: 4, jumlahAnggota: 32, totalReferal: 41 })
    const wrapper = render()
    await vi.waitFor(() => expect(wrapper.text()).toContain('Belum ada pendaftar lewat kode Anda. Bagikan kode dari menu Kode Referal.'))
    expect(wrapper.text()).toContain('Belum ada referal')
    expect(wrapper.text()).toContain('-')
  })

  it('menawarkan coba lagi saat gagal memuat', async () => {
    anggotaService.ringkasan.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({
      jumlahReferal: 3, peringkat: 2, jumlahAnggota: 32, totalReferal: 41,
    })
    const wrapper = render()
    await vi.waitFor(() => expect(wrapper.text()).toContain('Ringkasan belum bisa dimuat.'))
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(wrapper.text()).toContain('2 dari 32'))
    expect(anggotaService.ringkasan).toHaveBeenCalledTimes(2)
  })
})
