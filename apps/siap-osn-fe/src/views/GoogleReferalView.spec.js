import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import { api } from '@/lib/api.js'
import { routes, setupGuard } from '@/router/index.js'
import { useAuthStore } from '@/stores/auth.js'
import GoogleReferalView from '@/views/GoogleReferalView.vue'

// Hanya lapisan HTTP yang di-mock. View, store, authService, mapper user, dan
// guard router dipakai asli, karena bug yang dicegah di sini muncul dari
// gabungan semuanya: bentuk respons POST /auth/referal salah dibaca sehingga
// role user kosong, lalu guard melempar siswa ke /forbidden.
vi.mock('@/lib/api.js', () => ({
  TOKEN_KEY: 'siap_osn_token',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))
vi.mock('@/views/LandingPage.vue', () => ({ default: { name: 'LandingPage' } }))
vi.mock('@/views/LoginView.vue', () => ({ default: { name: 'LoginView' } }))

// Bentuk UserResource Laravel untuk akun Google baru.
const userLaravel = {
  id: 7,
  name: 'Budi Google',
  email: 'budi@example.com',
  roles: ['siswa'],
  kode_referal: 'AB23',
  jumlah_diundang: 0,
  created_at: '2026-11-07T00:00:00Z',
}

async function bukaHalamanReferal() {
  const pinia = createPinia()
  setActivePinia(pinia)

  // Akun Google baru baru saja menukar kode di /oauth/google/selesai.
  api.post.mockResolvedValueOnce({
    data: {
      message: 'Login berhasil',
      data: { user: userLaravel, token: 'tok', perlu_kode_referal: true },
    },
  })
  const auth = useAuthStore()
  await auth.masukDenganGoogle('kode-sekali-pakai')
  auth.initialized = true

  const router = setupGuard(createRouter({ history: createMemoryHistory(), routes }))
  await router.push('/oauth/google/referal')

  const wrapper = mount(GoogleReferalView, { global: { plugins: [pinia, router] } })
  return { wrapper, router, auth }
}

function tombol(wrapper, teks) {
  return wrapper.findAll('button').find((b) => b.text() === teks)
}

beforeEach(() => {
  vi.resetAllMocks()
  localStorage.clear()
})

describe('GoogleReferalView', () => {
  it('lewati mengirim kode kosong dan mendarat di dashboard siswa', async () => {
    const { wrapper, router, auth } = await bukaHalamanReferal()
    expect(router.currentRoute.value.name).toBe('oauth.google.referal')

    api.post.mockResolvedValueOnce({
      data: { message: 'Kode referal dilewati', data: { user: userLaravel } },
    })
    await tombol(wrapper, 'Lewati').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenLastCalledWith('/auth/referal', { kode_referal: null })
    // Dashboard dimuat lazy, jadi perpindahan route butuh waktu nyata.
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('siswa.dashboard'), { timeout: 5000 })
    expect(auth.user.role).toBe('siswa')
    expect(auth.perluKodeReferal).toBe(false)
  })

  it('kode valid dikirim apa adanya lalu mendarat di dashboard siswa', async () => {
    const { wrapper, router, auth } = await bukaHalamanReferal()

    api.post.mockResolvedValueOnce({
      data: { message: 'Kode referal berhasil disimpan', data: { user: userLaravel } },
    })
    await wrapper.find('input').setValue('K7XW')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(api.post).toHaveBeenLastCalledWith('/auth/referal', { kode_referal: 'K7XW' })
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('siswa.dashboard'), { timeout: 5000 })
    expect(auth.user.role).toBe('siswa')
    expect(auth.perluKodeReferal).toBe(false)
  })

  it('kode tidak ditemukan menampilkan pesan dan tetap di halaman referal', async () => {
    const { wrapper, router, auth } = await bukaHalamanReferal()

    api.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'The given data was invalid.',
          errors: { kode_referal: ['Kode referal tidak ditemukan.'] },
        },
      },
    })
    await wrapper.find('input').setValue('ZZZZ')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Kode referal tidak ditemukan.')
    expect(router.currentRoute.value.name).toBe('oauth.google.referal')
    expect(auth.perluKodeReferal).toBe(true)
  })
})
