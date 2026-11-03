<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import { useProgressStore } from '@/stores/progress.js'
import UserMenu from '@/components/UserMenu.vue'

const auth = useAuthStore()
const progress = useProgressStore()
const router = useRouter()

onMounted(() => {
  void progress.fetchDashboard()
})

const d = computed(() => progress.data)
const nama = computed(() => auth.nama || 'Siswa')
const sudahPreTest = computed(() => d.value.preTestSelesai)

function mulaiPretest() {
  router.push({ name: 'siswa.pretest' })
}

function bukaPemetaan() {
  router.push({ name: 'siswa.pemetaan' })
}

function bukaMateri() {
  router.push({ name: 'siswa.materi' })
}

function bukaRiwayat() {
  router.push({ name: 'siswa.riwayat' })
}

function muatUlang() {
  void progress.fetchDashboard({ force: true })
}

function formatTanggal(iso) {
  if (!iso) return '—'
  const tanggal = new Date(iso)
  if (Number.isNaN(tanggal.getTime())) return '—'

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(tanggal)
}

const subjudul = computed(() => {
  if (!sudahPreTest.value) return 'Semangat memulai persiapanmu!'
  return d.value.tingkat
    ? `Semangat melanjutkan persiapan Tingkat ${d.value.tingkat}-mu`
    : 'Semangat melanjutkan persiapan OSN-mu'
})

const jumlahPrioritasTersisa = computed(() => {
  const rincian = d.value.syaratSimulasi?.rincian ?? []
  if (!rincian.length) return d.value.rekomendasi.length

  return rincian.filter((item) => {
    if (!item.selesai) return true
    if (item.latihanBelumTersedia) return false
    return item.nilaiLatihan == null || item.nilaiLatihan < item.batas
  }).length
})

const tingkatBerikutnya = computed(() =>
  String(d.value.tingkat || '').toLowerCase().includes('provinsi')
    ? 'Nasional'
    : 'Provinsi',
)

const banner = computed(() => {
  if (d.value.perluPretestUlang) {
    return {
      judul: 'Kuota Simulasi Putaran Ini Habis',
      isi: 'Ambil pre-test ulang untuk memulai putaran baru dan mendapatkan peta materi yang diperbarui.',
    }
  }

  if (!sudahPreTest.value) {
    return {
      judul: 'Mulai Perjalanan OSN Informatika-mu!',
      isi: 'Kamu belum mengikuti tes pemetaan awal. Silakan ambil pre-test terlebih dahulu agar sistem dapat mendeteksi kekuatanmu dan membuka kurikulum belajar yang tepat sasaran.',
    }
  }

  const tingkat = String(d.value.tingkat || 'Kabupaten')
  const judul = tingkat.toLowerCase().includes('provinsi')
    ? 'Kamu makin dekat ke Provinsi!'
    : 'Kamu makin dekat ke Olimpiade Kabupaten!'
  const jumlah = jumlahPrioritasTersisa.value
  const kebutuhan = jumlah > 0
    ? `Selesaikan ${jumlah} materi prioritas`
    : 'Materi prioritasmu sudah tuntas'
  return {
    judul,
    isi: `${kebutuhan} & raih nilai simulasi ≥ 80 untuk buka Tingkat ${tingkatBerikutnya.value}.`,
  }
})

const catatanRataRata = computed(() => {
  if (!sudahPreTest.value || d.value.simulasiDiikuti === 0) return 'Belum ada Nilai'
  const delta = d.value.perubahanNilaiSimulasi
  if (delta == null) return `Dari ${d.value.simulasiDiikuti} simulasi`
  if (delta > 0) return `+${delta} dari simulasi sebelumnya`
  if (delta < 0) return `${delta} dari simulasi sebelumnya`
  return 'Sama dari simulasi sebelumnya'
})

const stats = computed(() => {
  const materiSelesai = sudahPreTest.value ? d.value.materiSelesai : 0
  const materiTotal = sudahPreTest.value ? d.value.materiTotal : 0
  const simulasiDiikuti = sudahPreTest.value ? d.value.simulasiDiikuti : 0
  const progressPersen = sudahPreTest.value ? progress.progressPersen : 0

  return [
    {
      value: `${progressPersen}%`,
      label: 'Progress Belajar',
      note: materiTotal ? `${materiSelesai} dari ${materiTotal} materi selesai` : 'Belum ada progres',
      positive: progressPersen > 0,
    },
    {
      value: String(sudahPreTest.value ? d.value.rataRataNilai : 0),
      label: 'Rata-rata Nilai Simulasi',
      note: catatanRataRata.value,
      positive: simulasiDiikuti > 0 && (d.value.perubahanNilaiSimulasi ?? 0) >= 0,
    },
    {
      value: `${materiSelesai}/${materiTotal}`,
      label: 'Materi Diselesaikan',
      note: materiTotal
        ? `${Math.max(0, materiTotal - materiSelesai)} materi tersisa`
        : 'Belum ada materi yang diambil',
      positive: materiSelesai > 0,
    },
    {
      value: String(simulasiDiikuti),
      label: 'Simulasi Diikuti',
      note: simulasiDiikuti ? `Tingkat ${d.value.tingkat || 'Kabupaten'}` : 'Belum mengikuti simulasi',
      positive: simulasiDiikuti > 0,
    },
  ]
})

const kompetensiPlaceholder = [
  'Struktur Data',
  'Algoritma Greedy',
  'Graf & Pohon',
  'Dynamic Programming',
  'Matematika Diskrit',
]

const rekomendasiPlaceholder = [
  { judul: 'Dynamic Programming Dasar', sub: 'Belum dikuasai', badge: 'Pelajari' },
  { judul: 'Graf & Pohon Lanjutan', sub: 'Perlu latihan', badge: 'Latihan' },
  { judul: 'Algoritma Greedy', sub: 'Perlu latihan', badge: 'Latihan' },
]

const riwayatPlaceholder = [
  'Simulasi Seleksi — Tingkat Kabupaten',
  'Latihan — Struktur Data',
  'Simulasi Seleksi — Tingkat Provinsi',
  'Pre-Test — Tingkat Kabupaten',
]

const stageItems = computed(() => {
  const levels = d.value.tingkatan?.length
    ? d.value.tingkatan.slice(0, 2)
    : [{ nama: 'Kabupaten' }, { nama: 'Provinsi' }]

  return levels.map((level, index) => ({
    ...level,
    state: !sudahPreTest.value ? 'locked' : index === 0 ? 'done' : 'next',
  }))
})

const stageProgress = computed(() => {
  if (!sudahPreTest.value) return 0
  return Math.min(100, Math.max(30, progress.progressPersen))
})

const stageHint = computed(() => {
  if (!sudahPreTest.value) {
    return 'Ikuti pre-test terlebih dahulu untuk membuka perjalanan tingkat dan melihat progres belajarmu.'
  }

  const jumlah = jumlahPrioritasTersisa.value
  const kebutuhan = jumlah > 0
    ? `Tinggal selesaikan ${jumlah} materi prioritas`
    : 'Materi prioritas sudah tuntas'
  return `${kebutuhan} dan raih nilai simulasi ≥ 80 untuk membuka Tingkat ${tingkatBerikutnya.value}. Semangat, dikit lagi! 💪`
})

const hasil = computed(() => d.value.hasilTerakhir)
const hasilPersen = computed(() => Math.min(100, Math.max(0, Number(hasil.value?.nilai ?? 0))))
const radius = 31
const keliling = 2 * Math.PI * radius
const dashHasil = computed(() => `${(hasilPersen.value / 100) * keliling} ${keliling}`)

const perubahanLabel = computed(() => {
  const delta = d.value.perubahanNilaiSimulasi
  if (delta == null) return ''
  if (delta > 0) return `Naik ${delta} poin dari simulasi sebelumnya 🎉`
  if (delta < 0) return `Turun ${Math.abs(delta)} poin dari simulasi sebelumnya`
  return 'Nilai sama dengan simulasi sebelumnya'
})

function durasiLabel(menit) {
  if (menit == null || !Number.isFinite(Number(menit))) return '—'
  return `${Math.round(menit)}m`
}

function nilaiClass(nilai) {
  if (nilai == null) return 'text-[#71809a]'
  if (Number(nilai) >= 70) return 'text-[#0c9b61]'
  if (Number(nilai) >= 40) return 'text-[#d18a00]'
  return 'text-[#dc4b43]'
}

function ikonRiwayat(jenis) {
  if (jenis === 'simulasi') return '▣'
  if (jenis === 'latihan') return '▤'
  if (jenis === 'pretest') return '✓'
  return '•'
}
</script>

<template>
  <div class="min-h-full bg-[#f4f7fc] font-sans text-[#101d34]">
    <header class="flex min-h-[72px] items-center justify-between gap-4 border-b border-[#e5ebf4] bg-white/90 px-5 py-3 sm:px-7">
      <div class="min-w-0">
        <h1 class="text-[17px] font-bold leading-tight">Halo {{ nama }}! 👋</h1>
        <p class="mt-1 truncate text-[12px] text-[#71809a] sm:text-[13px]">{{ subjudul }}</p>
      </div>
      <UserMenu />
    </header>

    <main class="space-y-4 px-4 py-5 sm:px-5 lg:px-6">
      <div
        v-if="progress.loading && !progress.loaded"
        class="rounded-xl border border-[#e5ebf4] bg-white px-5 py-3 text-[12px] text-[#71809a]"
      >
        Memuat data dashboard…
      </div>

      <div
        v-if="progress.error"
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#f3caca] bg-[#fff1f0] px-4 py-3 text-[12px] text-[#a63832]"
      >
        <span>Data dashboard belum dapat dimuat. Periksa koneksi internetmu.</span>
        <button
          type="button"
          class="rounded-lg border border-[#e7b6b2] bg-white px-3 py-1.5 font-semibold text-[#96342e] hover:bg-[#fff8f7]"
          @click="muatUlang"
        >
          ↻ Coba Lagi
        </button>
      </div>

      <!-- Banner utama -->
      <section class="relative flex min-h-[146px] items-center overflow-hidden rounded-[22px] bg-gradient-to-r from-[#0b2143] via-[#123b70] to-[#20599a] px-6 py-6 text-white sm:px-7 lg:px-8">
        <div class="relative z-10 max-w-[68%] sm:max-w-[62%]">
          <h2 class="text-[19px] font-bold leading-tight sm:text-[21px]">{{ banner.judul }}</h2>
          <p class="mt-2 max-w-[620px] text-[12px] leading-relaxed text-white/80 sm:text-[13px]">
            {{ banner.isi }}
          </p>
          <button
            v-if="d.perluPretestUlang"
            type="button"
            class="mt-3 rounded-lg bg-white px-4 py-2 text-[12px] font-bold text-[#102b52] transition hover:bg-[#edf4ff]"
            @click="mulaiPretest"
          >
            Ambil Pre-Test Ulang →
          </button>
        </div>

        <!-- Ilustrasi dekoratif agar banner tetap hidup tanpa aset eksternal -->
        <svg
          viewBox="0 0 360 180"
          class="absolute bottom-0 right-0 h-[150px] w-[310px] max-w-[45%] sm:h-[170px] sm:w-[360px]"
          fill="none"
          aria-hidden="true"
        >
          <circle cx="258" cy="91" r="88" fill="#082A55" opacity=".58" />
          <path d="M26 146L335 146L360 180H0L26 146Z" fill="#0878E8" />
          <path d="M45 146H322L339 161H31L45 146Z" fill="#0098FF" />
          <path d="M58 107H105V146H58V107Z" fill="#071B39" />
          <path d="M114 94H168V146H114V94Z" fill="#071B39" />
          <path d="M184 108H235V146H184V108Z" fill="#071B39" />
          <path d="M64 112H99V135H64V112Z" fill="#0B5EA8" />
          <path d="M121 100H160V134H121V100Z" fill="#0B5EA8" />
          <path d="M191 114H228V136H191V114Z" fill="#0B5EA8" />
          <path d="M75 120H90V122H75V120Z" fill="#45C5FF" />
          <path d="M134 111H150V113H134V111Z" fill="#45C5FF" />
          <path d="M202 123H217V125H202V123Z" fill="#45C5FF" />
          <!-- podium -->
          <path d="M104 129H155V164H104V129Z" fill="#0064CE" />
          <path d="M155 115H216V164H155V115Z" fill="#0078EA" />
          <path d="M216 132H275V164H216V132Z" fill="#0064CE" />
          <path d="M104 129L155 129L165 139H94L104 129Z" fill="#1AA4FF" />
          <path d="M155 115L216 115L226 126H145L155 115Z" fill="#28B5FF" />
          <path d="M216 132L275 132L285 143H206L216 132Z" fill="#1AA4FF" />
          <!-- student left -->
          <circle cx="77" cy="67" r="10" fill="#F6B58D" />
          <path d="M67 63C68 49 88 48 91 63L85 59L72 60L67 63Z" fill="#152B50" />
          <path d="M70 79L86 78L97 110L65 110L70 79Z" fill="#F5C344" />
          <path d="M69 85L54 67L59 63L77 79L69 85Z" fill="#F6B58D" />
          <path d="M85 83L96 66L101 69L94 91L85 83Z" fill="#F6B58D" />
          <path d="M69 108L65 129H76L80 109H69Z" fill="#123D82" />
          <path d="M83 108L85 129H96L91 108H83Z" fill="#123D82" />
          <!-- student center -->
          <circle cx="184" cy="53" r="11" fill="#F2AE87" />
          <path d="M173 51C174 34 195 35 197 51L190 45L178 46L173 51Z" fill="#142849" />
          <path d="M169 69L198 68L209 115H159L169 69Z" fill="#D735B7" />
          <path d="M173 72L157 53L162 49L180 67L173 72Z" fill="#F2AE87" />
          <path d="M196 72L211 51L216 55L204 79L196 72Z" fill="#F2AE87" />
          <path d="M171 112L166 132H179L183 113L171 112Z" fill="#153E83" />
          <path d="M188 113L192 132H205L199 111L188 113Z" fill="#153E83" />
          <!-- student right -->
          <circle cx="267" cy="75" r="9" fill="#ECA77E" />
          <path d="M258 71C259 57 276 58 278 71L271 66L261 68L258 71Z" fill="#112748" />
          <path d="M256 87L276 86L289 116H249L256 87Z" fill="#08C8D4" />
          <path d="M256 91L240 76L244 72L263 85L256 91Z" fill="#ECA77E" />
          <path d="M276 91L287 78L292 82L284 101L276 91Z" fill="#ECA77E" />
          <path d="M257 114L254 132H265L268 115L257 114Z" fill="#123D82" />
          <path d="M272 114L275 132H286L281 113L272 114Z" fill="#123D82" />
          <!-- trophy -->
          <path d="M172 19H201L198 37C196 49 188 55 186 55C182 55 174 48 173 37L172 19Z" fill="#FFC331" />
          <path d="M173 25H164C163 36 168 42 177 43" stroke="#FFC331" stroke-width="5" stroke-linecap="round" />
          <path d="M200 25H210C211 36 206 42 197 43" stroke="#FFC331" stroke-width="5" stroke-linecap="round" />
          <path d="M184 54V66M175 67H194" stroke="#FFC331" stroke-width="5" stroke-linecap="round" />
          <path d="M181 19L185 10L190 19" stroke="#FFE58A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M153 17L155 11M149 14L157 15" stroke="#FFC331" stroke-width="2" stroke-linecap="round" />
          <path d="M220 42L224 35M219 35L226 40" stroke="#42C7FF" stroke-width="2" stroke-linecap="round" />
        </svg>
      </section>

      <!-- Statistik ringkas -->
      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="stat in stats.slice(0, 3)"
          :key="stat.label"
          class="min-h-[104px] rounded-2xl border border-[#e3eaf4] bg-white px-4 py-4 sm:px-5"
        >
          <p class="text-[23px] font-bold leading-none tracking-tight">{{ stat.value }}</p>
          <p class="mt-1.5 text-[11px] text-[#71809a] sm:text-[12px]">{{ stat.label }}</p>
          <p
            class="mt-1 text-[10px] font-semibold sm:text-[11px]"
            :class="stat.positive ? 'text-[#0b9c64]' : 'text-[#e0443e]'"
          >
            {{ stat.note }}
          </p>
        </article>

        <article
          v-if="progress.simulasiError"
          class="flex min-h-[104px] flex-col items-start justify-center rounded-2xl border border-[#e3eaf4] bg-white px-4 py-3 sm:px-5"
        >
          <p class="text-[11px] font-bold">Gagal Memuat</p>
          <p class="mt-1 text-[10px] leading-relaxed text-[#68758b]">Terjadi kesalahan, silakan coba lagi.</p>
          <button
            type="button"
            class="mt-2 rounded-lg border border-[#dce4ef] px-2.5 py-1 text-[10px] font-semibold text-[#153b70] hover:bg-[#f4f7fc]"
            @click="muatUlang"
          >
            ↻ Coba Lagi
          </button>
        </article>
        <article
          v-else
          class="min-h-[104px] rounded-2xl border border-[#e3eaf4] bg-white px-4 py-4 sm:px-5"
        >
          <p class="text-[23px] font-bold leading-none tracking-tight">{{ stats[3].value }}</p>
          <p class="mt-1.5 text-[11px] text-[#71809a] sm:text-[12px]">{{ stats[3].label }}</p>
          <p
            class="mt-1 text-[10px] font-semibold sm:text-[11px]"
            :class="stats[3].positive ? 'text-[#0b9c64]' : 'text-[#e0443e]'"
          >
            {{ stats[3].note }}
          </p>
        </article>
      </section>

      <!-- Pemetaan dan rekomendasi -->
      <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_1fr]">
        <div class="flex min-w-0 flex-col gap-4">
          <!-- Pemetaan Kompetensi -->
          <article class="min-h-[260px] rounded-2xl border border-[#e3eaf4] bg-white p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-[14px] font-bold">Pemetaan Kompetensi</h3>
              <button
                v-if="sudahPreTest && d.kompetensi.length"
                type="button"
                class="shrink-0 text-[11px] font-bold text-[#123f7b] hover:underline"
                @click="bukaPemetaan"
              >
                Detail →
              </button>
              <span
                v-else-if="!sudahPreTest"
                class="rounded-full bg-[#fff0c8] px-3 py-1 text-[10px] font-semibold text-[#956000]"
              >
                Perlu Pre-Test
              </span>
            </div>

            <div
              v-if="!sudahPreTest"
              class="relative mt-5 min-h-[170px]"
            >
              <ul class="pointer-events-none select-none space-y-4 opacity-40 blur-[3px]" aria-hidden="true">
                <li v-for="namaMateri in kompetensiPlaceholder" :key="namaMateri" class="flex items-center gap-3 text-[12px]">
                  <span class="h-5 w-5 rounded-md bg-[#e7b5b5]"></span>
                  <span class="flex-1">{{ namaMateri }}</span>
                  <span class="text-[10px]">0%</span>
                </li>
              </ul>
              <div class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                <p class="text-[14px] font-bold">Data Pemetaan Belum Tersedia</p>
                <p class="mt-1.5 max-w-sm text-[11px] leading-relaxed text-[#66748b]">
                  Ikuti pre-test untuk melihat perbandingan skor kemampuanmu terhadap standar penguasaan materi.
                </p>
              </div>
            </div>

            <div
              v-else-if="progress.materiError || progress.pemetaanError"
              class="flex min-h-[185px] flex-col items-center justify-center text-center"
            >
              <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#ffe1df] text-[23px] text-[#d92f2f]">!</span>
              <p class="mt-3 text-[12px] font-bold">Gagal Memuat Pemetaan Kompetensi</p>
              <p class="mt-1 text-[11px] text-[#71809a]">Terjadi kesalahan, silakan coba lagi.</p>
              <button
                type="button"
                class="mt-3 rounded-lg border border-[#dce4ef] px-3 py-1.5 text-[10px] font-semibold text-[#153b70] hover:bg-[#f4f7fc]"
                @click="muatUlang"
              >
                ↻ Coba Lagi
              </button>
            </div>

            <div v-else-if="progress.materiLoading || progress.pemetaanLoading" class="mt-6 space-y-5">
              <div v-for="item in 4" :key="item" class="animate-pulse">
                <div class="mb-2 h-3 w-1/3 rounded bg-[#edf1f7]"></div>
                <div class="h-2 rounded-full bg-[#edf1f7]"></div>
              </div>
            </div>

            <div v-else-if="d.kompetensi.length" class="mt-5 space-y-3.5">
              <div v-for="item in d.kompetensi" :key="item.nama" class="grid grid-cols-[118px_minmax(0,1fr)_34px] items-center gap-3 text-[11px]">
                <span class="truncate font-semibold text-[#18243a]" :title="item.nama">{{ item.nama }}</span>
                <span class="h-2 overflow-hidden rounded-full bg-[#f0f3f8]">
                  <span
                    class="block h-full rounded-full"
                    :class="item.skor < 40 ? 'bg-gradient-to-r from-[#f2b21e] to-[#ffd56b]' : 'bg-gradient-to-r from-[#4b83e8] to-[#91baff]'"
                    :style="{ width: Math.max(0, Math.min(100, item.skor)) + '%' }"
                  ></span>
                </span>
                <span class="text-right font-semibold text-[#64738d]">{{ item.skor }}%</span>
              </div>
            </div>

            <div v-else class="flex min-h-[180px] flex-col items-center justify-center text-center">
              <p class="text-[13px] font-bold">Pemetaan Belum Tersedia</p>
              <p class="mt-1 text-[11px] text-[#71809a]">Hasil pemetaan akan muncul setelah pre-test selesai dinilai.</p>
            </div>
          </article>

          <!-- Riwayat Terbaru -->
          <article class="min-h-[250px] rounded-2xl border border-[#e3eaf4] bg-white p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-[14px] font-bold">Riwayat Terbaru</h3>
              <button
                v-if="sudahPreTest && d.riwayat.length"
                type="button"
                class="shrink-0 text-[11px] font-bold text-[#123f7b] hover:underline"
                @click="bukaRiwayat"
              >
                Lihat semua
              </button>
            </div>

            <div v-if="!sudahPreTest" class="relative mt-5 min-h-[170px]">
              <ul class="pointer-events-none select-none space-y-4 opacity-40 blur-[3px]" aria-hidden="true">
                <li v-for="(item, index) in riwayatPlaceholder" :key="index" class="flex items-center gap-3">
                  <span class="h-8 w-8 rounded-lg bg-[#e3eaf4]"></span>
                  <span class="flex-1 text-[11px]">{{ item }}</span>
                  <span class="text-[10px]">0</span>
                </li>
              </ul>
              <div class="absolute inset-0 flex items-center justify-center">
                <p class="text-[14px] font-bold">Belum Ada Riwayat</p>
              </div>
            </div>

            <div v-else-if="progress.riwayatError" class="flex min-h-[170px] flex-col items-center justify-center text-center">
              <p class="text-[13px] font-bold">Gagal Memuat Riwayat</p>
              <p class="mt-1 text-[11px] text-[#71809a]">Silakan coba muat ulang.</p>
              <button type="button" class="mt-3 rounded-lg border border-[#dce4ef] px-3 py-1.5 text-[10px] font-semibold text-[#153b70]" @click="muatUlang">↻ Coba Lagi</button>
            </div>

            <div v-else-if="progress.riwayatLoading" class="mt-5 space-y-4">
              <div v-for="item in 3" :key="item" class="h-10 animate-pulse rounded-lg bg-[#f3f6fa]"></div>
            </div>

            <ul v-else-if="d.riwayat.length" class="mt-4 divide-y divide-dashed divide-[#e5ebf3]">
              <li v-for="(item, index) in d.riwayat" :key="item.referensiId || item.tanggal || index" class="flex items-center gap-3 py-3 first:pt-1 last:pb-0">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#edf3fc] text-[13px] font-bold text-[#244c86]">
                  {{ ikonRiwayat(item.jenis) }}
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-[11px] font-bold text-[#172238]">{{ item.judul }}</span>
                  <span class="mt-0.5 block text-[10px] text-[#71809a]">{{ formatTanggal(item.tanggal) }}</span>
                </span>
                <span v-if="item.nilai != null" class="text-[13px] font-bold" :class="nilaiClass(item.nilai)">{{ Math.round(item.nilai) }}</span>
              </li>
            </ul>

            <div v-else class="flex min-h-[160px] items-center justify-center text-center text-[11px] text-[#71809a]">
              Belum ada aktivitas yang tercatat.
            </div>
          </article>
        </div>

        <div class="flex min-w-0 flex-col gap-4">
          <!-- Rekomendasi -->
          <article class="min-h-[260px] flex-1 rounded-2xl border border-[#e3eaf4] bg-white p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-[14px] font-bold">Rekomendasi Buat Kamu</h3>
              <button
                v-if="sudahPreTest && d.rekomendasi.length"
                type="button"
                class="shrink-0 text-[11px] font-bold text-[#123f7b] hover:underline"
                @click="bukaMateri"
              >
                Semua
              </button>
              <span
                v-else-if="!sudahPreTest"
                class="rounded-full bg-[#fff0c8] px-3 py-1 text-[10px] font-semibold text-[#956000]"
              >
                Perlu Pre-Test
              </span>
            </div>

            <div v-if="!sudahPreTest" class="relative mt-5 min-h-[185px]">
              <ul class="pointer-events-none select-none space-y-4 opacity-40 blur-[3px]" aria-hidden="true">
                <li v-for="item in rekomendasiPlaceholder" :key="item.judul" class="flex items-center gap-3">
                  <span class="h-8 w-8 rounded-lg bg-[#f1d1c8]"></span>
                  <span class="flex-1">
                    <span class="block text-[11px] font-bold">{{ item.judul }}</span>
                    <span class="text-[10px]">{{ item.sub }}</span>
                  </span>
                  <span class="rounded-full bg-[#fff0c8] px-2 py-1 text-[9px]">{{ item.badge }}</span>
                </li>
              </ul>
              <div class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                <p class="text-[14px] font-bold">Rekomendasi Belum Tersedia</p>
                <p class="mt-1.5 max-w-xs text-[11px] leading-relaxed text-[#66748b]">
                  Ikuti pre-test untuk melihat rekomendasi materi berdasarkan hasil skormu.
                </p>
              </div>
            </div>

            <ul v-else-if="d.rekomendasi.length" class="mt-4 divide-y divide-dashed divide-[#e5ebf3]">
              <li v-for="item in d.rekomendasi.slice(0, 4)" :key="item.materiId || item.judul" class="py-3 first:pt-1 last:pb-0">
                <button type="button" class="flex w-full items-center gap-3 text-left" @click="bukaMateri">
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-[11px] font-bold text-[#172238]">{{ item.judul }}</span>
                    <span class="mt-0.5 block text-[10px] text-[#71809a]">{{ item.sub }}</span>
                  </span>
                  <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold"
                    :class="item.badge === 'Pelajari' ? 'bg-[#ffe6e2] text-[#c43d2d]' : 'bg-[#fff1d5] text-[#a46b00]'"
                  >
                    {{ item.badge || 'Latihan' }}
                  </span>
                </button>
              </li>
            </ul>

            <div v-else class="flex min-h-[185px] flex-col items-center justify-center text-center">
              <p class="text-[13px] font-bold">Belum Ada Rekomendasi</p>
              <p class="mt-1 max-w-xs text-[11px] leading-relaxed text-[#71809a]">
                Rekomendasi akan muncul berdasarkan hasil pemetaan dan progres latihanmu.
              </p>
            </div>
          </article>

          <!-- Status Kenaikan Tingkat -->
          <article class="min-h-[220px] rounded-2xl border border-[#e3eaf4] bg-white p-5 sm:p-6">
            <h3 class="text-[14px] font-bold">Status Kenaikan Tingkat</h3>

            <div class="relative mx-auto mt-6 max-w-[390px]">
              <div class="absolute left-[8%] right-[8%] top-[15px] h-[3px] rounded-full bg-[#e4eaf3]"></div>
              <div
                class="absolute left-[8%] top-[15px] h-[3px] rounded-full bg-[#12a66a] transition-all"
                :style="{ width: `${stageProgress * .84}%` }"
              ></div>

              <div class="relative flex items-start justify-between">
                <div v-for="(stage, index) in stageItems" :key="stage.nama" class="z-10 flex w-24 flex-col items-center gap-2">
                  <span
                    class="flex h-8 w-8 items-center justify-center rounded-full border-2"
                    :class="stage.state === 'locked'
                      ? 'border-[#dce4ef] bg-[#f5f7fb] text-[#9aa7bb]'
                      : stage.state === 'done'
                        ? 'border-[#18ad75] bg-[#18ad75] text-white'
                        : 'border-[#f0b323] bg-[#f0b323] text-[#172238]'"
                  >
                    <svg v-if="stage.state === 'done'" viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else-if="stage.state === 'locked'" viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                    <span v-else class="text-[15px] leading-none">★</span>
                  </span>
                  <span class="text-[11px] font-bold" :class="stage.state === 'next' ? 'text-[#b47b00]' : 'text-[#172238]'">
                    {{ stage.nama }}
                  </span>
                </div>
              </div>
            </div>

            <p
              class="mt-5 rounded-xl px-3.5 py-3 text-[11px] leading-relaxed text-[#60718d]"
              :class="sudahPreTest ? 'bg-[#eaf1ff]' : 'bg-[#f1f4f8]'"
            >
              {{ stageHint }}
            </p>
          </article>
        </div>
      </section>

      <!-- Hasil simulasi terakhir -->
      <section class="rounded-2xl border border-[#e3eaf4] bg-white p-5 sm:p-6">
        <h3 class="text-[14px] font-bold">Hasil Simulasi Terakhir</h3>

        <div v-if="hasil" class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-center">
          <div class="flex min-w-0 flex-1 items-center gap-4">
            <div class="relative h-[72px] w-[72px] shrink-0">
              <svg viewBox="0 0 80 80" class="h-full w-full -rotate-90" aria-hidden="true">
                <circle cx="40" cy="40" :r="radius" fill="none" stroke="#edf1f6" stroke-width="7" />
                <circle
                  cx="40"
                  cy="40"
                  :r="radius"
                  fill="none"
                  stroke="#f2ad1c"
                  stroke-width="7"
                  stroke-linecap="round"
                  :stroke-dasharray="dashHasil"
                />
              </svg>
              <span class="absolute inset-0 flex items-center justify-center text-[13px] font-bold">{{ hasilPersen }}%</span>
            </div>

            <div class="min-w-0">
              <p class="truncate text-[12px] font-bold text-[#172238]">
                {{ (hasil.judul || 'Simulasi Seleksi').replace('Simulasi —', 'Simulasi Seleksi —') }}
              </p>
              <p class="mt-1 text-[10px] text-[#71809a]">
                {{ formatTanggal(hasil.tanggal) }}
                <template v-if="hasil.jumlahBenar != null"> · {{ hasil.jumlahBenar }} benar</template>
              </p>
              <p v-if="perubahanLabel" class="mt-1 text-[10px] font-semibold" :class="(d.perubahanNilaiSimulasi ?? 0) >= 0 ? 'text-[#0b9c64]' : 'text-[#d94a43]'">
                {{ perubahanLabel }}
              </p>
              <p v-else-if="progress.hasilDetailError" class="mt-1 text-[10px] text-[#71809a]">
                Ringkasan detail belum dapat dimuat.
              </p>
            </div>
          </div>

          <div class="grid flex-1 grid-cols-3 gap-2 sm:gap-3 lg:max-w-[600px]">
            <div class="rounded-xl bg-[#f3f6fb] px-2 py-3 text-center">
              <p class="text-[16px] font-bold">{{ hasil.jumlahBenar ?? '—' }}</p>
              <p class="mt-0.5 text-[10px] text-[#71809a]">Benar</p>
            </div>
            <div class="rounded-xl bg-[#f3f6fb] px-2 py-3 text-center">
              <p class="text-[16px] font-bold">{{ hasil.jumlahSalah ?? '—' }}</p>
              <p class="mt-0.5 text-[10px] text-[#71809a]">Salah</p>
            </div>
            <div class="rounded-xl bg-[#f3f6fb] px-2 py-3 text-center">
              <p class="text-[16px] font-bold">{{ durasiLabel(hasil.durasiMenit) }}</p>
              <p class="mt-0.5 text-[10px] text-[#71809a]">Durasi</p>
            </div>
          </div>
        </div>

        <div v-else class="flex min-h-[100px] flex-col items-center justify-center text-center">
          <p class="text-[13px] font-bold">Belum Ada Hasil Simulasi</p>
          <p class="mt-1 text-[11px] text-[#71809a]">Ikuti simulasi seleksi untuk melihat ringkasan hasil terakhirmu di sini.</p>
        </div>
      </section>
    </main>
  </div>
</template>
