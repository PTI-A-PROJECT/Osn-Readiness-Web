import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { authService } from '@/services/auth.js'
import { dashboardFor } from '@/router/index.js'
import GoogleSelesaiView from '@/views/GoogleSelesaiView.vue'

// Service di-mock total (lihat §1.3.5 + §5.4); dashboardFor di-mock agar
// spec tidak memuat seluruh peta route asli.
vi.mock('@/services/auth.js', () => ({
  authService: {
    tokenTersimpan: vi.fn(),
    simpanToken: vi.fn(),
    me: vi.fn(),
    login: vi.fn(),
    register: vi.fn(),
    exchangeGoogle: vi.fn(),
    updateProfile: vi.fn(),
    logout: vi.fn(),
  },
}))

vi.mock('@/router/index.js', () => ({
  dashboardFor: vi.fn(() => '/dashboard-siswa'),
}))

const userSiswa = {
  id: 1,
  nama: 'Budi',
  email: 'budi@example.com',
  role: 'siswa',
}

function buatRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard-siswa', name: 'dashboard-siswa', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
}

async function mountView() {
  const router = buatRouter()
  // Route pemanggil harus ada agar replace() tidak gagal di memory history.
  await router.push('/')
  const wrapper = mount(GoogleSelesaiView, { global: { plugins: [createPinia(), router] } })
  // onMounted memutar tukaran() secara async: tunggu mikrotask, lalu
  // setTimeout 800ms sebelum dashboard dipindahkan.
  await wrapper.vm.$nextTick()
  await vi.runAllTimersAsync()
  return { wrapper, router }
}

beforeEach(() => {
  vi.resetAllMocks()
  authService.tokenTersimpan.mockReturnValue(null)
  localStorage.clear()
  window.location.hash = ''
})

describe('GoogleSelesaiView', () => {
  it('kode #code= ditukar, hasil disimpan, lalu dipindah ke dashboard', async () => {
    vi.useFakeTimers()
    authService.exchangeGoogle.mockResolvedValue({ user: userSiswa, token: 'tok-google' })
    window.location.hash = '#code=kode-rahasia'

    await mountView()

    expect(authService.exchangeGoogle).toHaveBeenCalledWith('kode-rahasia')
    expect(dashboardFor).toHaveBeenCalledWith('siswa')
    expect(authService.simpanToken).toHaveBeenCalledWith('tok-google')

    // Fragmen telah dihapus dari address bar sejak awal mount.
    expect(window.location.hash).toBe('')
    vi.useRealTimers()
  })

  it('fragmen #error= google_taut menampilkan pesan akun tidak digabung otomatis', async () => {
    vi.useFakeTimers()
    window.location.hash = '#error=google_taut'

    const { wrapper, router } = await mountView()

    expect(authService.exchangeGoogle).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Masuk dengan kata sandi')
    // Tetap di halaman selesai, gagal jelas.
    expect(router.currentRoute.value.path).toBe('/')
    vi.useRealTimers()
  })

  it('kode kedua (sudah dipakai) menjatuhkan keadaan gagal dengan pesan', async () => {
    vi.useFakeTimers()
    authService.exchangeGoogle.mockRejectedValue({
      response: { status: 401, data: { message: 'Kode tidak sah', kode: 'KODE_GOOGLE_TIDAK_VALID' } },
    })
    window.location.hash = '#code=kode-pakai'

    const { wrapper } = await mountView()

    expect(authService.exchangeGoogle).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Kode login tidak sah')
    vi.useRealTimers()
  })
})
