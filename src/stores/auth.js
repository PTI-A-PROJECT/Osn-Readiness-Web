import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api.js'
import { useProgressStore } from '@/stores/progress.js'

// Analogi Laravel: Auth::user() di sisi browser.
// Token TIDAK PERNAH disimpan di sini — cookie httpOnly diurus browser.
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isSiswa = computed(() => user.value?.role === 'siswa')

  // Nama yang dipakai di sapaan & avatar. Kosong ('') kalau siswa belum mengisi nama.
  // Beberapa nama field dicoba supaya tetap jalan kalau backend memakai penamaan lain.
  const nama = computed(() => {
    const u = user.value ?? {}
    return String(u.nama ?? u.nama_lengkap ?? u.name ?? u.full_name ?? '').trim()
  })

  // Dipanggil sekali oleh router guard saat aplikasi dibuka.
  async function fetchMe() {
  try {
    const { data } = await api.get('/auth/me')
    // sesuaikan dengan bentuk respons backend-mu, misalnya data.user atau data.data
    user.value = data?.user ?? null
  } catch {
    user.value = null
  } finally {
    initialized.value = true
  }
}

  async function login(payload) {
    const { data } = await api.post('/auth/login', payload)
    useProgressStore().$reset() // pastikan tidak ada sisa data akun sebelumnya
    user.value = data.data.user
    return user.value
  }

  // Tidak otomatis login — pemanggil redirect ke /login.
  async function register(payload) {
    const { data } = await api.post('/auth/register', payload)
    return data.data
  }

  // Simpan perubahan profil (nama, email, sekolah, kelas) dari halaman Profil.
  // Karena sapaan & avatar membaca user.value, nama baru langsung tampil di mana-mana.
  async function updateProfile(payload) {
    const { data } = await api.put('/auth/profile', payload)
    user.value = { ...user.value, ...payload, ...(data?.data ?? {}) }
    return user.value
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // Abaikan: sesi lokal tetap dibersihkan agar user kembali ke /login.
    } finally {
      user.value = null
      useProgressStore().$reset()
    }
  }

  function $reset() {
    user.value = null
    initialized.value = false
    useProgressStore().$reset()
  }

  return {
    user,
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
