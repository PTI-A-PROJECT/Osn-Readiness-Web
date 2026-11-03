import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { dashboardKosong } from '@/services/mappers/dashboard.js'
import { belajarService } from '@/services/belajar.js'
import { pretestService } from '@/services/pretest.js'
import { riwayatService } from '@/services/riwayat.js'
import { simulasiService } from '@/services/simulasi.js'
import { siswaService } from '@/services/siswa.js'
import { usePretestStore } from '@/stores/pretest.js'
import { useRiwayatStore } from '@/stores/riwayat.js'

// Satu sumber data untuk dashboard siswa dan streak di sidebar.
// Data utama berasal dari GET /dashboard; agregat diambil dari endpoint
// materi, pre-test, simulasi, dan riwayat agar tiap bagian tetap independen.
const SEGAR_MS = 60_000

export const useProgressStore = defineStore('progress', () => {
  const data = ref(dashboardKosong())
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref(false)

  const materiLoading = ref(false)
  const materiError = ref(false)
  const pemetaanLoading = ref(false)
  const pemetaanError = ref(false)
  const simulasiLoading = ref(false)
  const simulasiError = ref(false)
  const hasilDetailError = ref(false)
  const riwayatLoading = ref(false)
  const riwayatError = ref(false)

  let inflight = null
  let controller = null
  let dimuatPada = 0

  const progressPersen = computed(() => {
    const { materiSelesai, materiTotal } = data.value
    return materiTotal ? Math.round((materiSelesai / materiTotal) * 100) : 0
  })

  function fetchDashboard({ force = false } = {}) {
    if (inflight && !force) return inflight
    if (!force && loaded.value && Date.now() - dimuatPada < SEGAR_MS) {
      return Promise.resolve()
    }

    controller?.abort()
    if (force) {
      materiLoading.value = false
      pemetaanLoading.value = false
      simulasiLoading.value = false
      riwayatLoading.value = false
    }
    const requestController = new AbortController()
    controller = requestController
    const { signal } = requestController
    loading.value = true
    error.value = false

    inflight = siswaService
      .dashboard({ signal })
      .then((hasil) => {
        if (signal.aborted) return
        data.value = {
          ...dashboardKosong(),
          ...hasil,
          preTestSelesai: hasil.preTestSelesai,
        }
        loaded.value = true
        dimuatPada = Date.now()

        // Bagian-bagian ini dimuat terpisah. Kegagalan satu endpoint tidak
        // menyembunyikan statistik atau komponen lain yang sudah berhasil.
        void muatStatistikMateri(signal)
        void muatStatistikSimulasi(signal)
        void muatRiwayat(signal)
      })
      .catch(() => {
        if (!signal.aborted) error.value = true
      })
      .finally(() => {
        if (controller !== requestController) return
        loading.value = false
        inflight = null
      })

    return inflight
  }

  async function muatStatistikMateri(signal) {
    materiLoading.value = true
    materiError.value = false
    pemetaanLoading.value = true
    pemetaanError.value = false

    try {
      // Tanpa pre-test, progres materi dan pemetaan belum boleh dihitung.
      if (!data.value.preTestSelesai || !data.value.tingkatAktifId) {
        data.value = {
          ...data.value,
          materiSelesai: 0,
          materiTotal: 0,
          kompetensi: [],
          rekomendasi: [],
        }
        return
      }

      const pretest = usePretestStore()
      await pretest.muatTingkat()
      if (signal?.aborted) return

      const tingkatTerbuka = pretest.tingkatList.filter((t) => t.terbuka)
      const ids = tingkatTerbuka.map((t) => t.id)
      const perTingkat = await Promise.all(
        ids.map((id) => belajarService.daftar({ tingkatId: id, signal })),
      )
      if (signal?.aborted) return

      const semuaMateri = perTingkat.flat()
      const indexTingkatAktif = ids.findIndex((id) => Number(id) === Number(data.value.tingkatAktifId))
      const materiAktif = perTingkat[indexTingkatAktif] ?? []
      const materiById = new Map(semuaMateri.map((materi) => [Number(materi.id), materi]))

      let kompetensi = materiAktif
        .filter((materi) => materi.nilaiTerbaik != null)
        .map((materi) => ({
          nama: materi.judul,
          skor: Math.round(materi.nilaiTerbaik),
          target: null,
        }))

      let rekomendasi = materiAktif
        .filter((materi) => materi.wajib)
        .sort((a, b) => (a.prioritas ?? 999) - (b.prioritas ?? 999))
        .map((materi) => {
          const skor = materi.nilaiTerbaik == null ? null : Math.round(materi.nilaiTerbaik)
          return {
            materiId: materi.id,
            judul: materi.judul,
            sub: skor == null ? 'Belum dikuasai' : skor < 70 ? 'Perlu latihan' : 'Sudah dikuasai',
            badge: skor == null || skor < 40 ? 'Pelajari' : skor < 70 ? 'Latihan' : 'Ulangi',
          }
        })

      // Gunakan pemetaan pre-test terbaru untuk tingkat aktif. Nilai latihan
      // di daftar materi tetap menjadi fallback bila hasil pre-test lama
      // tidak lagi tersedia di endpoint riwayat.
      try {
        const riwayatPretest = await riwayatService.daftar({
          jenis: 'pretest',
          tingkatId: data.value.tingkatAktifId,
          perPage: 1,
          signal,
        })
        if (signal?.aborted) return

        const pretestId = riwayatPretest.items[0]?.referensiId
        if (pretestId) {
          const paket = await pretestService.lihat({ id: pretestId, signal })
          if (signal?.aborted) return

          if (paket.jenis === 'hasil') {
            const hasilPretest = paket.hasil
            if (hasilPretest.pemetaan.length) {
              kompetensi = hasilPretest.pemetaan.map((item) => ({
                nama: materiById.get(Number(item.materiId))?.judul ?? `Materi ${item.materiId}`,
                skor: item.persentase == null ? 0 : Math.round(item.persentase),
                target: null,
              }))
            }

            if (hasilPretest.materiWajib.length) {
              const skorById = new Map(
                hasilPretest.pemetaan.map((item) => [Number(item.materiId), item.persentase]),
              )
              rekomendasi = hasilPretest.materiWajib
                .slice()
                .sort((a, b) => a.prioritas - b.prioritas)
                .map((item) => {
                  const materi = materiById.get(Number(item.materiId))
                  const skor = skorById.get(Number(item.materiId))
                  return {
                    materiId: item.materiId,
                    judul: materi?.judul ?? `Materi ${item.materiId}`,
                    sub: skor == null ? 'Belum dikuasai' : skor < 70 ? 'Perlu latihan' : 'Perlu penguatan',
                    badge: skor == null || skor < 40 ? 'Pelajari' : 'Latihan',
                  }
                })
            }
          }
        }
      } catch {
        // Nilai latihan tetap dapat dipakai sebagai fallback pemetaan.
        pemetaanError.value = true
      }

      if (signal?.aborted) return
      data.value = {
        ...data.value,
        materiSelesai: semuaMateri.filter((materi) => materi.progress?.status === 'selesai').length,
        materiTotal: semuaMateri.length,
        kompetensi,
        rekomendasi,
      }
    } catch {
      if (!signal?.aborted) {
        materiError.value = true
        pemetaanError.value = true
      }
    } finally {
      if (!signal?.aborted) {
        materiLoading.value = false
        pemetaanLoading.value = false
      }
    }
  }

  async function muatStatistikSimulasi(signal) {
    simulasiLoading.value = true
    simulasiError.value = false
    hasilDetailError.value = false

    try {
      const hasilRiwayat = await riwayatService.daftar({
        jenis: 'simulasi',
        perPage: 100,
        signal,
      })
      if (signal?.aborted) return

      const nilai = hasilRiwayat.items
        .map((item) => item.nilai)
        .filter((item) => item != null && Number.isFinite(Number(item)))
      const perubahan = nilai.length > 1
        ? Math.round(Number(nilai[0]) - Number(nilai[1]))
        : null

      data.value = {
        ...data.value,
        simulasiDiikuti: hasilRiwayat.total,
        rataRataNilai: nilai.length
          ? Math.round(nilai.reduce((total, item) => total + Number(item), 0) / nilai.length)
          : 0,
        perubahanNilaiSimulasi: perubahan,
      }

      const idTerakhir = data.value.hasilTerakhir?.id ?? hasilRiwayat.items[0]?.referensiId
      if (idTerakhir) {
        try {
          const paket = await simulasiService.lihat({ id: idTerakhir, signal })
          if (signal?.aborted) return

          if (paket.jenis === 'hasil') {
            const hasilSimulasi = paket.hasil
            const mulai = hasilSimulasi.mulaiPada ? new Date(hasilSimulasi.mulaiPada).getTime() : NaN
            const selesai = hasilSimulasi.selesaiPada ? new Date(hasilSimulasi.selesaiPada).getTime() : NaN
            const durasiMenit = Number.isFinite(mulai) && Number.isFinite(selesai) && selesai >= mulai
              ? Math.round((selesai - mulai) / 60000)
              : null

            data.value = {
              ...data.value,
              hasilTerakhir: {
                ...data.value.hasilTerakhir,
                id: hasilSimulasi.id,
                nilai: hasilSimulasi.nilai == null
                  ? data.value.hasilTerakhir?.nilai ?? null
                  : Math.round(hasilSimulasi.nilai),
                jumlahBenar: hasilSimulasi.jumlahBenar,
                jumlahSalah: hasilSimulasi.jumlahSalah,
                durasiMenit,
                lulus: hasilSimulasi.lulus,
              },
            }
          }
        } catch {
          hasilDetailError.value = true
        }
      }
    } catch {
      if (!signal?.aborted) simulasiError.value = true
    } finally {
      if (!signal?.aborted) simulasiLoading.value = false
    }
  }

  async function muatRiwayat(signal) {
    riwayatLoading.value = true
    riwayatError.value = false

    try {
      const items = await useRiwayatStore().terbaris({ jumlah: 5, signal })
      if (signal?.aborted) return
      data.value = { ...data.value, riwayat: items }
    } catch {
      if (!signal?.aborted) riwayatError.value = true
    } finally {
      if (!signal?.aborted) riwayatLoading.value = false
    }
  }

  // Dipanggil saat login/logout supaya data akun sebelumnya tidak terlihat.
  function $reset() {
    controller?.abort()
    controller = null
    inflight = null
    dimuatPada = 0
    data.value = dashboardKosong()
    loading.value = false
    loaded.value = false
    error.value = false
    materiLoading.value = false
    materiError.value = false
    pemetaanLoading.value = false
    pemetaanError.value = false
    simulasiLoading.value = false
    simulasiError.value = false
    hasilDetailError.value = false
    riwayatLoading.value = false
    riwayatError.value = false
  }

  function markPreTestCompleted() {
    data.value = { ...data.value, preTestSelesai: true }
  }

  return {
    data,
    loading,
    loaded,
    error,
    materiLoading,
    materiError,
    pemetaanLoading,
    pemetaanError,
    simulasiLoading,
    simulasiError,
    hasilDetailError,
    riwayatLoading,
    riwayatError,
    progressPersen,
    fetchDashboard,
    markPreTestCompleted,
    $reset,
  }
})
