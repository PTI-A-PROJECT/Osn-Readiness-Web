<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'
import { usePretestStore } from '@/stores/pretest.js'
import { useProgressStore } from '@/stores/progress.js'
import { belajarService } from '@/services/belajar.js'
import { pretestService } from '@/services/pretest.js'
import { riwayatService } from '@/services/riwayat.js'
import { pesanError } from '@/lib/errors.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const pretest = usePretestStore()
const progress = useProgressStore()

const TAHAP_PRETEST_SELESAI = new Set([
  'BELAJAR',
  'SIAP_SIMULASI',
  'SIMULASI_BERJALAN',
  'PUTARAN_HABIS',
  'LULUS',
])

const loading = ref(true)
const loadError = ref(false)
const showSkipModal = ref(false)
const selectedLevelId = ref(null)
const materiByTingkat = ref({})
const pretestResults = ref([])

const levels = computed(() => [...pretest.tingkatList].sort((a, b) => a.urutan - b.urutan))
const provinceLevel = computed(() =>
  levels.value.find((t) => String(t.nama).toLowerCase().includes('provinsi')) ?? null,
)
const selectedLevel = computed(() =>
  levels.value.find((t) => t.id === selectedLevelId.value) ?? null,
)
const selectedMaterials = computed(() => materiByTingkat.value[selectedLevelId.value] ?? [])
const selectedPretests = computed(() =>
  pretestResults.value
    .filter((r) => r.tingkatId === selectedLevelId.value)
    .sort((a, b) => new Date(b.tanggal || 0).getTime() - new Date(a.tanggal || 0).getTime()),
)
const selectedPretestsTotal = computed(() => selectedPretests.value.length)
const selectedPretestAverage = computed(() => {
  const scores = selectedPretests.value.map((r) => r.nilai).filter((n) => n != null)
  return scores.length ? Math.round(scores.reduce((sum, n) => sum + n, 0) / scores.length) : null
})
const selectedMaterialsOpened = computed(() =>
  selectedMaterials.value.filter((m) => m.progress !== null).length,
)
const selectedExercisesDone = computed(() =>
  selectedMaterials.value.filter((m) => m.nilaiTerbaik != null).length,
)
const selectedExerciseAverage = computed(() => {
  const scores = selectedMaterials.value.map((m) => m.nilaiTerbaik).filter((n) => n != null)
  return scores.length ? Math.round(scores.reduce((sum, n) => sum + n, 0) / scores.length) : null
})
const weakestTopic = computed(() => {
  const latest = selectedPretests.value[0]
  const mapping = (latest?.pemetaan ?? [])
    .filter((p) => p.persentase != null)
    .sort((a, b) => a.persentase - b.persentase)[0]
  if (!mapping) return '—'
  const materi = selectedMaterials.value.find((m) => m.id === mapping.materiId)
  return materi?.judul ?? 'Topik belum tersedia'
})
const selectedStageComplete = computed(() => selectedLevel.value?.tahap === 'LULUS')
const banner = computed(() => {
  if (selectedStageComplete.value) {
    return {
      tone: 'success',
      title: 'Luar biasa, semua tahap tuntas!',
      body: 'Kamu menyelesaikan seluruh materi, latihan, dan simulasi pada tahap ' + selectedLevel.value.nama + '. Ini hasil konsistensi yang panjang.',
    }
  }

  const latest = selectedPretests.value[0]
  const previous = selectedPretests.value[1]
  if (latest && previous && latest.nilai != null && previous.nilai != null) {
    if (latest.nilai > previous.nilai) {
      return {
        tone: 'success',
        title: 'Selamat, nilaimu naik!',
        body: 'Kamu konsisten belajar dan hasilnya terlihat. Pertahankan ritme ini, materi berikutnya sudah menunggu.',
      }
    }
    if (latest.nilai < previous.nilai) {
      return {
        tone: 'warning',
        title: 'Tetap semangat, kamu pasti bisa!',
        body: 'Nilai terakhirmu turun sedikit, dan itu wajar saat materi makin sulit. Ulangi materi yang masih lemah, lalu coba latihan berikutnya.',
      }
    }
  }

  if (!latest) {
    return {
      tone: 'info',
      title: 'Satu langkah kecil untuk memulai',
      body: 'Kerjakan pre-test pada tahap ini, lalu kami tunjukkan materi mana yang paling perlu kamu kuatkan.',
    }
  }

  return {
    tone: 'info',
    title: 'Terus jaga progres belajarmu!',
    body: 'Setiap materi dan latihan yang kamu selesaikan membantumu semakin siap menghadapi seleksi OSN.',
  }
})

function daftarMateri(levelId) {
  return materiByTingkat.value[levelId] ?? []
}

function statusTingkat(level) {
  if (level.tahap === 'LULUS') return 'Tuntas'
  if (!level.terbuka) return 'Terkunci'
  return 'Sedang berjalan'
}

function ringkasanTingkat(level) {
  const list = daftarMateri(level.id)
  const dibuka = list.filter((m) => m.progress !== null).length
  const latihan = list.filter((m) => m.nilaiTerbaik != null).length
  return 'materi ' + dibuka + '/' + list.length + ' · latihan ' + latihan + '/' + list.length
}

function formatTanggal(value, short = false) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: short ? 'short' : 'long',
    year: 'numeric',
  }).format(date)
}

function formatNilai(value) {
  return value == null ? '—' : String(Math.round(value)) + '%'
}

function jumlahBenarLabel(result) {
  if (result.benar == null || result.totalSoal == null || result.totalSoal === 0) return '—'
  return result.benar + '/' + result.totalSoal
}

function trenLabel(result, index) {
  if (index === selectedPretests.value.length - 1) return 'Awal'
  const previous = selectedPretests.value[index + 1]
  if (result.nilai == null || previous?.nilai == null) return '—'
  if (result.nilai > previous.nilai) return '↑ Naik'
  if (result.nilai < previous.nilai) return '↓ Turun'
  return 'Stabil'
}

function trenClass(result, index) {
  const label = trenLabel(result, index)
  if (label.includes('Naik')) return 'bg-[#e6f7ef] text-[#128052]'
  if (label.includes('Turun')) return 'bg-[#fee8e7] text-[#c23b35]'
  return 'bg-[#eaf0ff] text-[#24458f]'
}

function statusMateri(materi) {
  if (materi.progress?.status === 'selesai') return 'Selesai dibaca'
  if (materi.progress) return 'Sedang dibaca'
  return 'Belum dibuka'
}

function statusMateriClass(materi) {
  if (materi.progress?.status === 'selesai') return 'bg-[#e1f7ed] text-[#128052]'
  if (materi.progress) return 'bg-[#fff4d9] text-[#9a6500]'
  return 'bg-[#eaf0ff] text-[#24458f]'
}

function nilaiClass(value) {
  if (value == null) return 'text-[#6b778c]'
  if (value < 70) return 'text-[#e0443e]'
  if (value < 85) return 'text-[#d58b00]'
  return 'text-[#15945c]'
}

function chartX(index) {
  const count = Math.max(selectedMaterials.value.length, 5)
  const slot = 650 / count
  return 75 + slot * index + slot / 2
}

function chartY(value) {
  return 220 - Math.max(0, Math.min(100, Number(value) || 0)) * 1.6
}

function chartHeight(value) {
  return Math.max(0, Math.min(100, Number(value) || 0)) * 1.6
}

const hasChartScores = computed(() =>
  selectedMaterials.value.some((m) => m.nilaiTerbaik != null),
)

async function muatRiwayat() {
  loading.value = true
  loadError.value = false
  try {
    await Promise.all([
      pretest.muatTingkat({ force: true }),
      progress.fetchDashboard({ force: true }),
    ])

    const lookup = Object.fromEntries(pretest.tingkatList.map((t) => [t.id, t.nama]))
    const [riwayatPretest, ...daftarMateriPerTingkat] = await Promise.all([
      riwayatService.daftar({
        jenis: 'pretest',
        perPage: 100,
        page: 1,
        namaTingkatById: lookup,
      }),
      ...pretest.tingkatList.map(async (t) => {
        try {
          return await belajarService.daftar({ tingkatId: t.id })
        } catch {
          return []
        }
      }),
    ])

    materiByTingkat.value = Object.fromEntries(
      pretest.tingkatList.map((t, index) => [t.id, daftarMateriPerTingkat[index] ?? []]),
    )

    const detailPretest = await Promise.all(
      riwayatPretest.items.map(async (item) => {
        try {
          const paket = await pretestService.lihat({ id: item.referensiId })
          const hasil = paket.jenis === 'hasil' ? paket.hasil : null
          const pemetaan = hasil?.pemetaan ?? []
          return {
            ...item,
            pemetaan,
            benar: pemetaan.reduce((sum, p) => sum + p.jumlahBenar, 0),
            totalSoal: pemetaan.reduce((sum, p) => sum + p.jumlahSoal, 0),
          }
        } catch {
          return { ...item, pemetaan: [], benar: null, totalSoal: null }
        }
      }),
    )
    pretestResults.value = detailPretest

    const defaultLevel =
      pretest.tingkatList.find((t) => t.id === progress.data.tingkatAktifId) ??
      pretest.tingkatList.find((t) => t.terbuka) ??
      pretest.tingkatList[0] ??
      null
    selectedLevelId.value = defaultLevel?.id ?? null

    const province = pretest.tingkatList.find((t) =>
      String(t.nama).toLowerCase().includes('provinsi'),
    )
    if (province) {
      const tahapSudahPretest = TAHAP_PRETEST_SELESAI.has(province.tahap)
      const adaHasilPretest = detailPretest.some((r) => r.tingkatId === province.id)
      showSkipModal.value = !(tahapSudahPretest || adaHasilPretest)
    }
  } catch (err) {
    loadError.value = true
    const province = pretest.tingkatList.find((t) =>
      String(t.nama).toLowerCase().includes('provinsi'),
    )
    if (province) {
      const tahapSudahPretest = TAHAP_PRETEST_SELESAI.has(province.tahap)
      const adaHasilPretest = pretestResults.value.some((r) => r.tingkatId === province.id)
      showSkipModal.value = !(tahapSudahPretest || adaHasilPretest)
    }
    toast.add({
      severity: 'error',
      summary: 'Gagal memuat riwayat',
      detail: pesanError(err),
      life: 4000,
    })
  } finally {
    loading.value = false
  }
}

function kerjakanPretestProvinsi() {
  const province = provinceLevel.value
  showSkipModal.value = false
  router.push({
    name: 'siswa.pretest',
    query: province ? { tingkatId: String(province.id) } : {},
  })
}

onMounted(muatRiwayat)
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] font-sans text-[#0f1b33]">
    <header class="flex items-center justify-between border-b border-[#e6ebf2] bg-white px-5 py-3.5 sm:px-7">
      <div>
        <h1 class="text-[17px] font-bold leading-tight">{{ route.meta.title || 'Riwayat Hasil' }}</h1>
        <p class="mt-0.5 text-[12px] text-[#71809a]">
          Semua jejak pre-test, materi, latihan, dan simulasi yang pernah kamu kerjakan
        </p>
      </div>
      <UserMenu />
    </header>

    <main class="space-y-5 px-4 py-5 sm:px-6 sm:py-6">
      <div
        v-if="loading"
        class="rounded-2xl border border-[#e6ebf2] bg-white px-5 py-4 text-sm text-[#71809a]"
      >
        Memuat riwayat dan progres belajarmu…
      </div>

      <div
        v-else-if="loadError && !levels.length"
        class="rounded-2xl border border-[#f3c2c2] bg-[#fdf0f0] px-5 py-4 text-sm text-[#a33333]"
      >
        Data riwayat belum dapat dimuat. Periksa koneksi lalu muat ulang halaman.
      </div>

      <template v-else>
        <div
          v-if="loadError"
          class="rounded-xl border border-[#f3d1a0] bg-[#fff8e8] px-4 py-3 text-[12px] text-[#8a5a00]"
        >
          Sebagian data belum dapat dimuat. Informasi yang tersedia tetap ditampilkan.
        </div>

        <!-- BANNER MOTIVASI DINAMIS -->
        <section
          class="rounded-2xl px-7 py-5 text-white sm:px-8"
          :class="{
            'bg-gradient-to-r from-[#087b4b] to-[#12a365]': banner.tone === 'success',
            'bg-gradient-to-r from-[#ad7518] to-[#df4439]': banner.tone === 'warning',
            'bg-gradient-to-r from-[#2455b9] to-[#2d62d2]': banner.tone === 'info',
          }"
        >
          <h2 class="text-[17px] font-bold">{{ banner.title }}</h2>
          <p class="mt-1.5 max-w-3xl text-[12px] leading-relaxed text-white/90">
            {{ banner.body }}
          </p>
        </section>

        <!-- 01 POSISI TAHAP -->
        <section class="border-b border-[#e4eaf2] pb-5">
          <p class="text-[10px] font-bold uppercase tracking-wide text-[#e28b00]">01 · Posisi Tahap</p>
          <h2 class="mt-1 text-[19px] font-bold leading-tight">Kamu ada di tahap mana</h2>
          <p class="mt-0.5 text-[12px] text-[#71809a]">
            Tahap dibuka satu per satu. Provinsi baru terbuka setelah Kabupaten tuntas.
          </p>

          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <button
              v-for="level in levels"
              :key="level.id"
              type="button"
              class="rounded-xl border bg-white px-4 py-3 text-left transition hover:border-[#f0a000]"
              :class="selectedLevelId === level.id ? 'border-2 border-[#f0a000]' : 'border-[#e1e8f2]'"
              @click="selectedLevelId = level.id"
            >
              <span class="block text-[14px] font-bold">{{ level.nama }}</span>
              <span
                class="mt-0.5 block text-[11px]"
                :class="level.tahap === 'LULUS' ? 'text-[#15945c]' : 'text-[#71809a]'"
              >
                {{ statusTingkat(level) }} · {{ ringkasanTingkat(level) }}
              </span>
            </button>
          </div>
        </section>

        <!-- 02 RIWAYAT PRE-TEST -->
        <section class="border-b border-[#e4eaf2] pb-5">
          <p class="text-[10px] font-bold uppercase tracking-wide text-[#e28b00]">02 · Riwayat Pre-Test</p>
          <h2 class="mt-1 text-[19px] font-bold leading-tight">Riwayat Pre-Test</h2>
          <p class="mt-0.5 text-[12px] text-[#71809a]">
            Pre-test hanya bisa dikerjakan satu kali di setiap tahap untuk memetakan kompetensi.
          </p>

          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#71809a]">Total Pre-Test</p>
              <p class="mt-1 text-[22px] font-bold leading-none">{{ selectedPretestsTotal }}x</p>
              <p class="mt-2 text-[10px] text-[#71809a]">dari {{ levels.length }} tahap</p>
            </article>
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#71809a]">Rata-rata Skor</p>
              <p class="mt-1 text-[22px] font-bold leading-none">
                {{ selectedPretestAverage == null ? '0%' : selectedPretestAverage + '%' }}
              </p>
              <p class="mt-2 text-[10px] text-[#71809a]">
                {{ selectedPretestAverage == null ? 'Belum ada pembanding' : 'Berdasarkan hasil tahap ini' }}
              </p>
            </article>
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#71809a]">Topik Paling Lemah</p>
              <p class="mt-1 truncate text-[19px] font-bold leading-tight">{{ weakestTopic }}</p>
            </article>
          </div>

          <div class="mt-3 overflow-x-auto rounded-xl border border-[#e4eaf2] bg-white px-3 py-4 sm:px-4">
            <table class="w-full min-w-[700px] border-collapse text-center text-[11px]">
              <thead>
                <tr class="border-b border-[#e8edf4] text-[#71809a]">
                  <th class="px-2 py-2 font-semibold">Tanggal</th>
                  <th class="px-2 py-2 font-semibold">Tingkat</th>
                  <th class="px-2 py-2 font-semibold">Skor</th>
                  <th class="px-2 py-2 font-semibold">Benar</th>
                  <th class="px-2 py-2 font-semibold">Durasi</th>
                  <th class="px-2 py-2 font-semibold">Tren</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(result, index) in selectedPretests"
                  :key="result.referensiId"
                  class="border-b border-[#edf1f6] last:border-0"
                >
                  <td class="px-2 py-2.5">{{ formatTanggal(result.tanggal, true) }}</td>
                  <td class="px-2 py-2.5">
                    <span class="rounded-full bg-[#eaf0ff] px-2.5 py-1 text-[10px] font-semibold text-[#24458f]">
                      {{ selectedLevel?.nama || '—' }}
                    </span>
                  </td>
                  <td class="px-2 py-2.5 font-bold" :class="nilaiClass(result.nilai)">
                    {{ formatNilai(result.nilai) }}
                  </td>
                  <td class="px-2 py-2.5">{{ jumlahBenarLabel(result) }}</td>
                  <td class="px-2 py-2.5 text-[#71809a]" title="Durasi pengerjaan belum disediakan oleh data riwayat.">—</td>
                  <td class="px-2 py-2.5">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="trenClass(result, index)">
                      {{ trenLabel(result, index) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!selectedPretests.length">
                  <td colspan="6" class="px-3 py-8 text-[12px] text-[#71809a]">
                    Belum ada riwayat pre-test pada tahap {{ selectedLevel?.nama || 'ini' }}.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 03 MATERI DAN LATIHAN -->
        <section>
          <div class="flex flex-wrap items-center gap-2">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-wide text-[#e28b00]">03 · Materi dan Latihan</p>
              <h2 class="mt-1 text-[19px] font-bold leading-tight">Materi dan Latihan Soal</h2>
              <p class="mt-0.5 text-[12px] text-[#71809a]">
                Progres materi, nilai latihan, dan grafik perkembangan di setiap tahap.
              </p>
            </div>
            <span
              v-if="selectedLevel"
              class="rounded-full bg-[#eaf0ff] px-3 py-1 text-[10px] font-semibold text-[#24458f]"
            >
              {{ statusTingkat(selectedLevel) }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#596273]">Materi dibuka</p>
              <p class="mt-1 text-[22px] font-bold leading-none">
                {{ selectedMaterialsOpened }}/{{ selectedMaterials.length }}
              </p>
              <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#edf1f6]">
                <div
                  class="h-full rounded-full bg-[#e9a000]"
                  :style="{ width: (selectedMaterials.length ? selectedMaterialsOpened / selectedMaterials.length * 100 : 0) + '%' }"
                ></div>
              </div>
              <p class="mt-2 text-[10px] text-[#71809a]">
                {{ Math.max(0, selectedMaterials.length - selectedMaterialsOpened) }} materi belum dibuka
              </p>
            </article>
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#596273]">Latihan dikerjakan</p>
              <p class="mt-1 text-[22px] font-bold leading-none">
                {{ selectedExercisesDone }}/{{ selectedMaterials.length }}
              </p>
              <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#edf1f6]">
                <div
                  class="h-full rounded-full bg-[#e9a000]"
                  :style="{ width: (selectedMaterials.length ? selectedExercisesDone / selectedMaterials.length * 100 : 0) + '%' }"
                ></div>
              </div>
              <p class="mt-2 text-[10px] text-[#71809a]">
                {{ Math.max(0, selectedMaterials.length - selectedExercisesDone) }} latihan tersisa
              </p>
            </article>
            <article class="rounded-xl border border-[#e4eaf2] bg-white px-5 py-4">
              <p class="text-[11px] font-semibold text-[#596273]">Rata-rata nilai latihan</p>
              <p class="mt-1 text-[22px] font-bold leading-none" :class="nilaiClass(selectedExerciseAverage)">
                {{ selectedExerciseAverage == null ? '—' : selectedExerciseAverage }}
              </p>
              <p class="mt-2 text-[10px] text-[#71809a]">
                {{ selectedExerciseAverage == null ? 'Belum ada pembanding' : 'Rata-rata nilai terbaik per materi' }}
              </p>
            </article>
          </div>

          <!-- GRAFIK NILAI -->
          <article class="mt-3 rounded-xl border border-[#e4eaf2] bg-white p-5 sm:p-6">
            <h3 class="text-[12px] font-bold">Grafik nilai latihan per materi</h3>
            <div class="relative mt-4">
              <svg
                viewBox="0 0 760 285"
                class="block w-full"
                role="img"
                :aria-label="'Grafik nilai latihan untuk ' + (selectedLevel?.nama || 'tingkat terpilih')"
              >
                <g stroke="#dfe7f2" stroke-width="1.5">
                  <line x1="55" y1="60" x2="735" y2="60" />
                  <line x1="55" y1="140" x2="735" y2="140" />
                  <line x1="55" y1="220" x2="735" y2="220" />
                </g>
                <g fill="#71809a" font-size="10">
                  <text x="18" y="64">100</text>
                  <text x="25" y="144">50</text>
                  <text x="31" y="224">0</text>
                </g>
                <g v-for="(materi, index) in selectedMaterials" :key="materi.id">
                  <rect
                    v-if="materi.nilaiTerbaik != null"
                    :x="chartX(index) - 15"
                    :y="chartY(materi.nilaiTerbaik)"
                    width="30"
                    :height="chartHeight(materi.nilaiTerbaik)"
                    rx="5"
                    fill="#4d75d1"
                  />
                  <text
                    :x="chartX(index)"
                    :y="materi.nilaiTerbaik == null ? 210 : Math.max(48, chartY(materi.nilaiTerbaik) - 8)"
                    text-anchor="middle"
                    fill="#172b4d"
                    font-size="11"
                    font-weight="700"
                  >
                    {{ materi.nilaiTerbaik == null ? '—' : Math.round(materi.nilaiTerbaik) }}
                  </text>
                  <text
                    :x="chartX(index)"
                    y="250"
                    text-anchor="middle"
                    fill="#71809a"
                    font-size="10"
                  >
                    M{{ index + 1 }}
                  </text>
                </g>
                <g v-if="!selectedMaterials.length" fill="#71809a" font-size="10">
                  <text x="130" y="250">M1</text>
                  <text x="260" y="250">M2</text>
                  <text x="390" y="250">M3</text>
                  <text x="520" y="250">M4</text>
                  <text x="650" y="250">M5</text>
                </g>
              </svg>

              <div
                v-if="!hasChartScores"
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-white/55 text-center"
              >
                <p class="text-[13px] font-bold text-[#27354d]">Grafik Belum Tersedia</p>
                <p class="mt-1 max-w-sm text-[11px] text-[#71809a]">
                  Grafik nilai muncul setelah kamu mengerjakan latihan pertama.
                </p>
              </div>
            </div>
            <p class="mt-1 text-[10px] text-[#71809a]">
              Nilai di atas batang menunjukkan nilai terbaik pada masing-masing materi.
            </p>
          </article>

          <!-- TABEL DETAIL MATERI -->
          <div class="mt-3 overflow-x-auto rounded-xl border border-[#e4eaf2] bg-white px-3 py-4 sm:px-4">
            <table class="w-full min-w-[760px] border-collapse text-center text-[11px]">
              <thead>
                <tr class="border-b border-[#e8edf4] text-[#71809a]">
                  <th class="px-2 py-2 font-semibold">Tanggal</th>
                  <th class="px-2 py-2 font-semibold">Materi</th>
                  <th class="px-2 py-2 font-semibold">Status materi</th>
                  <th class="px-2 py-2 font-semibold">Jumlah soal</th>
                  <th class="px-2 py-2 font-semibold">Nilai</th>
                  <th class="px-2 py-2 font-semibold">Durasi</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="materi in selectedMaterials"
                  :key="materi.id"
                  class="border-b border-[#edf1f6] last:border-0"
                >
                  <td class="px-2 py-2.5">{{ formatTanggal(materi.progress?.tanggalSelesai, true) }}</td>
                  <td class="px-2 py-2.5 font-medium">{{ materi.judul }}</td>
                  <td class="px-2 py-2.5">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="statusMateriClass(materi)">
                      {{ statusMateri(materi) }}
                    </span>
                  </td>
                  <td class="px-2 py-2.5">{{ materi.jumlahSoalLatihan ?? '—' }}</td>
                  <td class="px-2 py-2.5 font-bold" :class="nilaiClass(materi.nilaiTerbaik)">
                    {{ materi.nilaiTerbaik == null ? 'Belum latihan' : Math.round(materi.nilaiTerbaik) }}
                  </td>
                  <td class="px-2 py-2.5 text-[#71809a]" title="Durasi latihan belum disediakan oleh data materi.">—</td>
                </tr>
                <tr v-if="!selectedMaterials.length">
                  <td colspan="6" class="px-3 py-8 text-[12px] text-[#71809a]">
                    Data materi untuk tahap ini belum tersedia.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </main>

    <!-- MODAL JIKA PRE-TEST PROVINSI BELUM DIKERJAKAN -->
    <div
      v-if="showSkipModal"
      class="fixed inset-0 z-[1000] flex items-center justify-center bg-[#071a35]/45 px-4 py-6 backdrop-blur-[3px]"
      role="presentation"
    >
      <section
        class="w-full max-w-[450px] rounded-[14px] bg-white p-6 shadow-[0_22px_60px_rgba(5,20,45,0.25)] sm:p-7"
        role="dialog"
        aria-modal="true"
        aria-labelledby="skip-pretest-title"
      >
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fff0d2] text-[22px] font-bold text-[#9a6500]">
            ?
          </span>
          <div class="min-w-0 pt-0.5">
            <h2 id="skip-pretest-title" class="text-[15px] font-bold text-[#14233e]">
              Lewati pre-test dulu?
            </h2>
            <p class="mt-1 text-[12px] text-[#71809a]">
              Kamu tetap bisa belajar, tetapi:
            </p>
          </div>
        </div>

        <ul class="mt-4 space-y-1 rounded-lg bg-[#fee3e3] px-4 py-3 text-[12px] leading-relaxed text-[#68758c]">
          <li>• Rekomendasi materi belum disesuaikan denganmu</li>
          <li>• Topik terlemah belum bisa ditentukan</li>
          <li>• Pre-test Provinsi bisa kamu kerjakan kapan saja, tetapi hanya 1 kali</li>
        </ul>

        <div class="mt-6 flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg bg-[#203f91] px-5 py-2.5 text-[12px] font-bold text-white transition hover:bg-[#183477]"
            @click="kerjakanPretestProvinsi"
          >
            Kerjakan Sekarang
          </button>
          <button
            type="button"
            class="rounded-lg border border-[#e1e8f2] bg-white px-5 py-2.5 text-[12px] font-semibold text-[#14233e] transition hover:bg-[#f5f8fc]"
            @click="showSkipModal = false"
          >
            Lewati dulu
          </button>
        </div>
      </section>
    </div>
  </div>
</template>
