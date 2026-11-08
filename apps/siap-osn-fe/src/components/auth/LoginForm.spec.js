import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia } from 'pinia'
import LoginForm from '@/components/auth/LoginForm.vue'
import { authService } from '@/services/auth.js'

vi.mock('@/router/index.js', () => ({ dashboardFor: vi.fn(() => '/x') }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: vi.fn() }) }))
vi.mock('@/services/auth.js', () => ({
  authService: {
    tokenTersimpan: vi.fn(() => null), simpanToken: vi.fn(), me: vi.fn(), login: vi.fn(),
    register: vi.fn(), exchangeGoogle: vi.fn(), simpanReferal: vi.fn(), updateProfile: vi.fn(), logout: vi.fn(),
  },
}))

function render() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/login', name: 'login', component: { template: '<div />' } }, { path: '/x', component: { template: '<div />' } }],
  })
  return { wrapper: mount(LoginForm, { global: { plugins: [createPinia(), router] } }), router }
}

beforeEach(() => vi.resetAllMocks())

describe('LoginForm identifier validation', () => {
  it('accepts NIM and calls login with the existing email interface', async () => {
    authService.login.mockResolvedValue({ user: { role: 'anggota' }, token: 'token' })
    const { wrapper } = render()
    await wrapper.get('#email').setValue('230533610158')
    await wrapper.get('#password').setValue('230533610158')
    await wrapper.get('form').trigger('submit')
    expect(authService.login).toHaveBeenCalledWith({ email: '230533610158', password: '230533610158', remember: false })
  })
  it('rejects malformed identifiers without calling login', async () => {
    const { wrapper } = render()
    await wrapper.get('#email').setValue('abc')
    await wrapper.get('#password').setValue('password')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Format email atau NIM tidak valid')
    expect(authService.login).not.toHaveBeenCalled()
  })

  it('requires an identifier', async () => {
    const { wrapper } = render()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Email atau NIM wajib diisi')
  })

  it('accepts email identifiers', async () => {
    authService.login.mockResolvedValue({ user: { role: 'siswa' }, token: 'token' })
    const { wrapper } = render()
    await wrapper.get('#email').setValue('ani@example.com')
    await wrapper.get('#password').setValue('password')
    await wrapper.get('form').trigger('submit')
    expect(authService.login).toHaveBeenCalledWith({ email: 'ani@example.com', password: 'password', remember: false })
  })
})
