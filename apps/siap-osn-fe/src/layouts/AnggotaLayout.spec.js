import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import AnggotaLayout from '@/layouts/AnggotaLayout.vue'
import { authService } from '@/services/auth.js'
import { useAuthStore } from '@/stores/auth.js'

vi.mock('@/services/auth.js', () => ({
  authService: {
    tokenTersimpan: vi.fn(() => null),
    simpanToken: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
  },
}))

beforeEach(() => vi.resetAllMocks())

describe('AnggotaLayout', () => {
  it('menyediakan navigasi anggota dan keluar menuju login', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const auth = useAuthStore()
    auth.user = { id: 3, nama: 'Ani', role: 'anggota' }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/anggota', name: 'anggota.dashboard', component: AnggotaLayout },
        { path: '/anggota/kode', name: 'anggota.kode', component: { template: '<div />' } },
        { path: '/login', name: 'login', component: { template: '<div />' } },
      ],
    })
    await router.push({ name: 'anggota.dashboard' })
    await router.isReady()
    const wrapper = mount(AnggotaLayout, { global: { plugins: [pinia, router] } })
    expect(wrapper.text()).toContain('Dashboard')
    expect(wrapper.text()).toContain('Kode Referal')
    await wrapper.get('button').trigger('click')
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('login'))
    expect(authService.logout).toHaveBeenCalledOnce()
  })
})
