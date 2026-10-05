import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { dashboardKosong } from '@/services/mappers/dashboard.js'
import { siswaService } from '@/services/siswa.js'

// Satu sumber data untuk semua angka di dashboard siswa dan streak di sidebar.
// Kontrak API + bentuk data: services/mappers/dashboard.js.

// Selama rentang ini data dianggap masih segar: pindah halaman lalu kembali
// ke dashboard tidak memicu request baru. Pakai { force: true } untuk memaksa.
const SEGAR_MS = 60_000

export const useProgressStore = defineStore('progress', () => {
  const data = ref(dashboardKosong())
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref(false)

  let inflight = null // mencegah request ganda (sidebar + dashboard sama-sama memanggil)
  let controller = null // membatalkan request milik akun lama saat $reset
  let dimuatPada = 0

  const progressPersen = computed(() => {
    const { materiSelesai, materiTotal } = data.value
    return materiTotal ? Math.round((materiSelesai / materiTotal) * 100) : 0
  })

  function fetchDashboard({ force = false } = {}) {
    if (inflight) return inflight
    if (!force && loaded.value && Date.now() - dimuatPada < SEGAR_MS) return Promise.resolve()

    controller = new AbortController()
    const { signal } = controller
    loading.value = true
    error.value = false

    inflight = siswaService
      .dashboard({ signal })
      .then((hasil) => {
        if (signal.aborted) return
        data.value = {
          ...hasil,
          preTestSelesai: hasil.preTestSelesai || data.value.preTestSelesai,
        }
        loaded.value = true
        dimuatPada = Date.now()
      })
      .catch(() => {
        // Data lama dibiarkan agar tampilan tidak salah menunjukkan "belum ada progres".
        if (!signal.aborted) error.value = true
      })
      .finally(() => {
        if (signal.aborted) return
        loading.value = false
        inflight = null
      })

    return inflight
  }

  // Wajib dipanggil saat login/logout supaya data siswa sebelumnya tidak terlihat akun berikutnya.
  function $reset() {
    controller?.abort()
    controller = null
    inflight = null
    dimuatPada = 0
    data.value = dashboardKosong()
    loading.value = false
    loaded.value = false
    error.value = false
  }

  function markPreTestCompleted() {
    data.value = { ...data.value, preTestSelesai: true }
  }

  return { data, loading, loaded, error, progressPersen, fetchDashboard, markPreTestCompleted, $reset }
})
