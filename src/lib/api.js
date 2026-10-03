import axios from 'axios'

// Kunci penyimpanan Bearer token Sanctum. Ditaruh di sini (bukan di
// stores/auth) agar tidak circular: api <- stores/auth <- router.
export const TOKEN_KEY = 'siap_osn_token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  withCredentials: true,
})

// Backend Laravel (Sanctum) memakai Bearer token, bukan cookie sesi.
// Token dibaca langsung dari localStorage agar tidak circular:
// api <- stores/auth <- router (lihat response interceptor di bawah).
api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const status = err.response?.status
    const url = err.config?.url ?? ''
    // 401 dari /auth/me (pengunjung anonim) dan 401 di halaman login
    // ("Email atau password salah") tidak boleh memicu redirect.
    // Import lazy agar tidak circular: api <- stores/auth <- router.
    if (status === 401 && !url.endsWith('/auth/me')) {
      const { default: router } = await import('@/router/index.js')
      if (router.currentRoute.value.name !== 'login') {
        const { useAuthStore } = await import('@/stores/auth.js')
        const auth = useAuthStore()
        if (typeof auth.$reset === 'function') auth.$reset()
        else auth.user = null
        router.push({
          name: 'login',
          query: { redirect: router.currentRoute.value.fullPath },
        })
      }
    }
    return Promise.reject(err)
  },
)
