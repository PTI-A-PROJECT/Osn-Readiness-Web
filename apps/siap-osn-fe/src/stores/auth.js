import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { kosongkan } from '@/lib/cache.js'
import { KUNCI_POPUP_PRETEST_LOGIN } from '@/lib/pretest.js'
import { authService } from '@/services/auth.js'
import { useLatihanStore } from '@/stores/latihan.js'
import { useMateriStore } from '@/stores/materi.js'
import { usePretestStore } from '@/stores/pretest.js'
import { useProgressStore } from '@/stores/progress.js'
import { useRiwayatStore } from '@/stores/riwayat.js'
import { useSimulasiStore } from '@/stores/simulasi.js'

// Store tidak tahu HTTP/backend: token persisten + request lewat
// authService, bentuk user dari services/mappers/user.js.

// Tidak cukup hanya progress — pretest/latihan/materi/riwayat/simulasi
// menyimpan soal, jawaban, dan id percobaan milik akun sebelumnya.
function resetDataAkun() {
  kosongkan() // cache GET milik akun sebelumnya
  useProgressStore().$reset()
  usePretestStore().$reset()
  useLatihanStore().$reset()
  useMateriStore().$reset()
  useRiwayatStore().$reset()
  useSimulasiStore().$reset()
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)
  const token = ref(authService.tokenTersimpan())
  // Hanya true sesaat setelah login Google akun baru; sumbernya backend.
  const perluKodeReferal = ref(false)

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
    authService.simpanToken(t)
  }

  // Tandai bahwa popup pretest harus tampil sekali setelah login/daftar.
  // Nilai yang disimpan adalah id user supaya tidak bocor ke akun lain.
  function tandaiPopupPretest() {
    try {
      // Hanya siswa yang punya alur pretest.
      if (user.value?.id != null && user.value?.role === 'siswa') {
        sessionStorage.setItem(KUNCI_POPUP_PRETEST_LOGIN, String(user.value.id))
      }
    } catch {
      // sessionStorage bisa diblokir; popup cukup tidak tampil.
    }
  }

  function hapusPenandaPopupPretest() {
    try {
      sessionStorage.removeItem(KUNCI_POPUP_PRETEST_LOGIN)
    } catch {
      // abaikan
    }
  }

  let meInflight = null // navigasi beruntun saat boot tidak memanggil /auth/me dua kali

  // Dipanggil sekali oleh router guard saat aplikasi dibuka.
  function fetchMe() {
    meInflight ??= muatUser().finally(() => {
      meInflight = null
    })
    return meInflight
  }

  // Jeda sebelum percobaan ulang /auth/me (300 ms lalu 900 ms).
  const JEDA_RETRY_ME = [300, 900]

  // Hanya kegagalan sementara yang layak dicoba ulang: jaringan/timeout
  // (tanpa respons) dan 5xx. 401/403/4xx berarti sesi memang tidak sah.
  function errorSementara(err) {
    const status = err?.response?.status
    if (status) return status >= 500
    return err?.code !== 'ERR_CANCELED'
  }

  function tunggu(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms))
  }

  async function muatUser() {
    if (!token.value) {
      user.value = null
      initialized.value = true
      return
    }
    try {
      for (let percobaan = 0; ; percobaan++) {
        try {
          user.value = await authService.me()
          return
        } catch (err) {
          // Server lambat tidak boleh langsung menghapus sesi siswa.
          // Setelah percobaan habis, token tetap dibersihkan agar guard
          // tidak terjebak loop redirect.
          if (percobaan >= JEDA_RETRY_ME.length || !errorSementara(err)) throw err
          await tunggu(JEDA_RETRY_ME[percobaan])
        }
      }
    } catch {
      user.value = null
      saveToken(null)
    } finally {
      initialized.value = true
    }
  }

  async function login(payload) {
    const res = await authService.login(payload)
    resetDataAkun() // pastikan tidak ada sisa data akun sebelumnya
    saveToken(res.token)
    user.value = res.user
    tandaiPopupPretest()
    return user.value
  }

  // Tidak otomatis login — pemanggil redirect ke /login.
  async function register(payload) {
    const res = await authService.register(payload)
    resetDataAkun()
    saveToken(res.token)
    user.value = res.user
    tandaiPopupPretest()
    return user.value
  }

  // Tukarkan kode sekali pakai hasil callback Google. Alurnya sama dengan
  // login: data akun sebelumnya dibuang dulu supaya tidak kabur campur.
  async function masukDenganGoogle(kode) {
    const res = await authService.exchangeGoogle(kode)
    resetDataAkun()
    saveToken(res.token)
    user.value = res.user
    perluKodeReferal.value = res.perluKodeReferal
    tandaiPopupPretest()
    return user.value
  }

  // Isi atau lewati kode referal sekali untuk akun Google baru. Setelah
  // berhasil flag dimatikan sehingga halaman ini tidak muncul lagi.
  async function simpanReferal(kodeReferal) {
    user.value = await authService.simpanReferal(kodeReferal)
    perluKodeReferal.value = false
    return user.value
  }

  // Simpan perubahan profil (nama, email) dari halaman Profil.
  // sekolah/kelas hanya disimpan lokal (belum ada kolomnya di backend).
  // Karena sapaan & avatar membaca user.value, nama baru langsung tampil di mana-mana.
  async function updateProfile(payload) {
    const terbaru = await authService.updateProfile({
      nama: payload.nama ?? payload.name,
      email: payload.email,
    })
    user.value = { ...user.value, ...payload, ...terbaru }
    return user.value
  }

  // Ubah kata sandi sendiri; sandi lama diverifikasi di backend.
  async function ubahSandi({ current, baru, konfirmasi }) {
    return authService.ubahSandi({ current, baru, konfirmasi })
  }

  async function logout() {
    try {
      await authService.logout()
    } catch {
      // Abaikan: sesi lokal tetap dibersihkan agar user kembali ke /login.
    } finally {
      user.value = null
      saveToken(null)
      hapusPenandaPopupPretest()
      resetDataAkun()
    }
  }

  function $reset() {
    user.value = null
    initialized.value = false
    saveToken(null)
    perluKodeReferal.value = false
    hapusPenandaPopupPretest()
    resetDataAkun()
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
    masukDenganGoogle,
    updateProfile,
    ubahSandi,
    logout,
    perluKodeReferal,
    simpanReferal,
    $reset,
  }
})
