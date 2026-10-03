import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, TOKEN_KEY } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapUser } from '@/lib/user.js'
import { useProgressStore } from '@/stores/progress.js'

// Backend Laravel (Sanctum): auth pakai Bearer token yang dikembalikan
// login/register sebagai `data.token`, disimpan di localStorage agar sesi
// bertahan setelah refresh. Lihat ARCHITECTURE_RULES §2 + lib/endpoints.js
// (daftar path) + lib/user.js (bentuk user).
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)
  const token = ref(localStorage.getItem(TOKEN_KEY))

  const isAuthenticated = computed(() => user.value !== null)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isSiswa = computed(() => user.value?.role === 'siswa')

  // Nama yang dipakai di sapaan & avatar. Kosong ('') kalau siswa belum mengisi nama.
  // Beberapa nama field dicoba supaya tetap jalan kalau backend memakai penamaan lain.
  const nama = computed(() => {
    const u = user.value ?? {}
    return String(u.nama ?? u.nama_lengkap ?? u.name ?? u.full_name ?? '').trim()
  })

  function saveToken(t) {
    token.value = t
    if (t) localStorage.setItem(TOKEN_KEY, t)
    else localStorage.removeItem(TOKEN_KEY)
  }

  // Dipanggil sekali oleh router guard saat aplikasi dibuka.
  async function fetchMe() {
    if (!token.value) {
      user.value = null
      initialized.value = true
      return
    }
    try {
      // GET /auth/me -> { message, data: user }
      const { data } = await api.get(ENDPOINTS.auth.me)
      user.value = mapUser(data.data)
    } catch {
      user.value = null
      saveToken(null)
    } finally {
      initialized.value = true
    }
  }

  async function login(payload) {
    // POST /auth/login {email, password} -> { message, data: {user, token} }
    const { data } = await api.post(ENDPOINTS.auth.login, payload)
    useProgressStore().$reset() // pastikan tidak ada sisa data akun sebelumnya
    saveToken(data.data.token)
    user.value = mapUser(data.data.user)
    return user.value
  }

  // Tidak otomatis login — pemanggil redirect ke /login.
  async function register(payload) {
    // Laravel wajib password_confirmation; FE memakai nama field `konfirmasi`.
    const { data } = await api.post(ENDPOINTS.auth.register, {
      name: payload.nama,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.konfirmasi,
    })
    return mapUser(data.data.user)
  }

  // Simpan perubahan profil (nama, email) dari halaman Profil.
  // sekolah/kelas hanya disimpan lokal (belum ada kolomnya di backend).
  // Karena sapaan & avatar membaca user.value, nama baru langsung tampil di mana-mana.
  async function updateProfile(payload) {
    const { data } = await api.put(ENDPOINTS.auth.profile, {
      name: payload.nama ?? payload.name,
      email: payload.email,
    })
    user.value = { ...user.value, ...payload, ...mapUser(data?.data) }
    return user.value
  }

  async function logout() {
    try {
      await api.post(ENDPOINTS.auth.logout)
    } catch {
      // Abaikan: sesi lokal tetap dibersihkan agar user kembali ke /login.
    } finally {
      user.value = null
      saveToken(null)
      useProgressStore().$reset()
    }
  }

  function $reset() {
    user.value = null
    initialized.value = false
    saveToken(null)
    useProgressStore().$reset()
  }

  return {
    user,
    token,
    initialized,
    isAuthenticated,
    isSuperAdmin,
    isSiswa,
    nama,
    fetchMe,
    login,
    register,
    updateProfile,
    logout,
    $reset,
  }
})
