<script setup>
// A6 · Log aktivitas user & Analisis Evaluasi Pembelajaran (khusus Super Admin).
// Mencakup:
// 1. Log Aktivitas User (timeline 90 hari)
// 2. Analisis Percobaan User (nyoba 1x, 2x, 3x+ beserta perbandingan nilainya)
// 3. Persentase Kali Keluar Soal (exposure rate & inverse weighted probability)
// 4. 4 Tes Klasik Soal (Reliabilitas KR-20, Validitas, Daya Pembeda, Tingkat Kesukaran)
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Select from 'primevue/select'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import { adminService } from '@/services/admin.js'
import { pesanError } from '@/lib/errors.js'

const SEMUA = 'semua'
const route = useRoute()
const router = useRouter()

const tabAktif = computed({
  get: () => {
    const t = String(route.query.tab || 'log')
    return ['log', 'percobaan', 'exposure', 'klasik'].includes(t) ? t : 'log'
  },
  set: (val) => {
    router.replace({ query: { ...route.query, tab: val } })
  },
})

const daftarTab = [
  { id: 'log', label: 'Log Aktivitas User' },
  { id: 'percobaan', label: 'Analisis Percobaan (1x vs 2x)' },
  { id: 'exposure', label: 'Persentase Kali Keluar Soal' },
  { id: 'klasik', label: '4 Tes Klasik Soal (CTT)' },
]

const siswaList = ref([])
const tingkatList = ref([])
const siswaId = ref(SEMUA)
const dari = ref('')
const sampai = ref('')
const kategori = ref(SEMUA)

// Filter analisis
const filterTingkatId = ref(SEMUA)
const filterPeruntukan = ref(SEMUA)
const filterStatusExposure = ref(SEMUA)
const filterKualitas = ref(SEMUA)
const cariSoal = ref('')

const memuatSiswa = ref(false)
const memuat = ref(false)
const memuatAnalisis = ref(false)
const galat = ref('')

const timeline = ref([])
const detail = ref({})
const membuka = ref('')
const dataAnalisis = ref(null)

const pilihanSiswa = computed(() => [
  { label: `Semua user (${siswaList.value.length})`, value: SEMUA },
  ...siswaList.value.map((s) => ({ label: `${s.nama} — ${s.email}`, value: s.id })),
])

const pilihanKategori = [
  { label: 'Semua kategori', value: SEMUA },
  { label: 'Akun', value: 'akun' },
  { label: 'Belajar', value: 'belajar' },
  { label: 'Admin', value: 'admin' },
]

const pilihanTingkat = computed(() => [
  { label: 'Semua tingkat seleksi', value: SEMUA },
  ...tingkatList.value.map((t) => ({ label: t.nama, value: t.id })),
])

const pilihanPeruntukan = [
  { label: 'Semua jenis tes (Pretest, Latihan, Simulasi)', value: SEMUA },
  { label: 'Pre-Test', value: 'pretest' },
  { label: 'Latihan Materi', value: 'latihan' },
  { label: 'Simulasi Seleksi', value: 'simulasi' },
]

const pilihanStatusExposure = [
  { label: 'Semua soal', value: SEMUA },
  { label: 'Sudah pernah keluar (> 0x)', value: 'pernah' },
  { label: 'Belum pernah keluar (0x — Prioritas Tinggi)', value: 'belum' },
]

const pilihanKualitas = [
  { label: 'Semua status kualitas', value: SEMUA },
  { label: 'Soal Baik (Pertahankan)', value: 'Soal Baik (Pertahankan)' },
  { label: 'Perlu Revisi Ringan', value: 'Perlu Revisi Ringan' },
  { label: 'Perlu Direvisi / Diganti', value: 'Perlu Direvisi / Diganti' },
  { label: 'Cacat / Cek Kunci Jawaban', value: 'Cacat / Cek Kunci Jawaban' },
  { label: 'Belum Cukup Data', value: 'Belum Cukup Data' },
]

// Ratakan timeline per tanggal menjadi baris tabel.
const baris = computed(() =>
  timeline.value.flatMap((hari) =>
    hari.entri.map((entri) => ({ ...entri, tanggal: hari.tanggal })),
  ),
)

const ringkasanPercobaan = computed(() => dataAnalisis.value?.ringkasan_percobaan ?? null)
const rincianPercobaan = computed(() => dataAnalisis.value?.rincian_percobaan ?? [])
const reliabilitas = computed(() => dataAnalisis.value?.reliabilitas ?? null)
const semuaButirSoal = computed(() => dataAnalisis.value?.butir_soal ?? [])

const ringkasanExposure = computed(() => {
  const list = semuaButirSoal.value
  const total = list.length
  const sudahKeluar = list.filter((b) => b.jumlah_keluar > 0).length
  const belumKeluar = total - sudahKeluar
  const totalKemunculan = list.reduce((acc, b) => acc + b.jumlah_keluar, 0)
  const rataKeluar = total > 0 ? (totalKemunculan / total).toFixed(2) : '0.00'
  return {
    total,
    sudahKeluar,
    belumKeluar,
    persenSudah: total > 0 ? Math.round((sudahKeluar / total) * 100) : 0,
    rataKeluar,
  }
})

const butirExposureTersaring = computed(() => {
  const q = cariSoal.value.trim().toLowerCase()
  return semuaButirSoal.value
    .filter((b) => {
      if (filterStatusExposure.value === 'pernah' && b.jumlah_keluar === 0) return false
      if (filterStatusExposure.value === 'belum' && b.jumlah_keluar > 0) return false
      if (
        q &&
        !b.kode_soal.toLowerCase().includes(q) &&
        !b.pertanyaan_ringkas.toLowerCase().includes(q) &&
        !b.materi_judul.toLowerCase().includes(q)
      ) {
        return false
      }
      return true
    })
    .slice()
    .sort((a, b) => b.jumlah_keluar - a.jumlah_keluar || a.soal_id - b.soal_id)
})

const butirKlasikTersaring = computed(() => {
  const q = cariSoal.value.trim().toLowerCase()
  return semuaButirSoal.value
    .filter((b) => {
      if (filterKualitas.value !== SEMUA && b.rekomendasi !== filterKualitas.value) return false
      if (
        q &&
        !b.kode_soal.toLowerCase().includes(q) &&
        !b.pertanyaan_ringkas.toLowerCase().includes(q) &&
        !b.materi_judul.toLowerCase().includes(q)
      ) {
        return false
      }
      return true
    })
    .slice()
    .sort((a, b) => b.jumlah_menjawab - a.jumlah_menjawab || a.soal_id - b.soal_id)
})

onMounted(async () => {
  memuatSiswa.value = true
  try {
    const [hasilSiswa, hasilTingkat] = await Promise.all([
      adminService.daftarSiswa({ perPage: 100 }),
      adminService.daftarTingkatAdmin(),
    ])
    siswaList.value = hasilSiswa.items
    tingkatList.value = hasilTingkat
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat daftar filter.')
  } finally {
    memuatSiswa.value = false
  }

  await Promise.all([muat(), muatAnalisis()])
})

watch([siswaId, dari, sampai, kategori], () => {
  void muat()
})

watch([siswaId, filterTingkatId, filterPeruntukan], () => {
  void muatAnalisis()
})

async function muat() {
  galat.value = ''
  memuat.value = true
  detail.value = {}
  membuka.value = ''

  try {
    timeline.value = await adminService.aktivitasSiswa({
      userId: siswaId.value === SEMUA ? null : siswaId.value,
      dari: dari.value || null,
      sampai: sampai.value || null,
      kategori: kategori.value === SEMUA ? null : kategori.value,
    })
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat log aktivitas.')
    timeline.value = []
  } finally {
    memuat.value = false
  }
}

async function muatAnalisis() {
  memuatAnalisis.value = true
  try {
    dataAnalisis.value = await adminService.analisisAktivitas({
      tingkatId: filterTingkatId.value === SEMUA ? null : filterTingkatId.value,
      peruntukan: filterPeruntukan.value === SEMUA ? null : filterPeruntukan.value,
      userId: siswaId.value === SEMUA ? null : siswaId.value,
    })
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat analisis evaluasi soal & percobaan.')
  } finally {
    memuatAnalisis.value = false
  }
}

function muatUlangAktif() {
  if (tabAktif.value === 'log') {
    void muat()
  } else {
    void muatAnalisis()
  }
}

function kunci(tanggal, entri) {
  return `${entri.user_id ?? 'x'}|${tanggal}|${entri.aksi}`
}

function perluDetail(entri) {
  return entri.kali > 1 || entri.kategori === 'akun'
}

async function buka(tanggal, entri) {
  const id = kunci(tanggal, entri)
  if (membuka.value === id) {
    membuka.value = ''
    return
  }

  membuka.value = id

  if (detail.value[id]) return

  try {
    detail.value[id] = await adminService.detailAktivitas({
      userId: entri.user_id ?? null,
      aksi: entri.aksi,
      tanggal,
    })
  } catch (err) {
    detail.value[id] = []
    galat.value = pesanError(err, 'Gagal memuat rincian aktivitas.')
  }
}

function tanggalPendek(tanggal) {
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${tanggal}T00:00:00`))
}

function jam(iso) {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso))
}

function labelKategori(k) {
  return { akun: 'Akun', belajar: 'Belajar', admin: 'Admin' }[k] ?? k
}

function warnaKategori(k) {
  return (
    {
      akun: 'bg-[#e8f0fb] text-[#0759a5]',
      belajar: 'bg-[#e9f7ee] text-[#1d7a42]',
      admin: 'bg-[#fdf3dd] text-[#8a6100]',
    }[k] ?? 'bg-[#eef1f6] text-[#56657d]'
  )
}

function warnaJenisTes(j) {
  return (
    {
      pretest: 'bg-[#e8f0fb] text-[#0759a5]',
      latihan: 'bg-[#e9f7ee] text-[#1d7a42]',
      simulasi: 'bg-[#fdf3dd] text-[#8a6100]',
    }[j] ?? 'bg-[#eef1f6] text-[#56657d]'
  )
}

function warnaKesukaran(kat) {
  if (kat.includes('Ideal')) return 'bg-[#e9f7ee] text-[#1d7a42]'
  if (kat.includes('Sukar')) return 'bg-[#fdecec] text-[#b42318]'
  if (kat.includes('Mudah')) return 'bg-[#e8f0fb] text-[#0759a5]'
  return 'bg-[#eef1f6] text-[#56657d]'
}

function warnaDayaBeda(kat) {
  if (kat === 'Sangat Baik' || kat === 'Baik') return 'bg-[#e9f7ee] text-[#1d7a42]'
  if (kat.includes('Cukup')) return 'bg-[#fdf3dd] text-[#8a6100]'
  if (kat.includes('Buruk') || kat.includes('Negatif')) return 'bg-[#fdecec] text-[#b42318]'
  return 'bg-[#eef1f6] text-[#56657d]'
}

function warnaRekomendasi(rek) {
  if (rek.includes('Soal Baik')) return 'bg-[#e9f7ee] text-[#1d7a42]'
  if (rek.includes('Revisi Ringan')) return 'bg-[#fdf3dd] text-[#8a6100]'
  if (rek.includes('Direvisi') || rek.includes('Cacat')) return 'bg-[#fdecec] text-[#b42318]'
  return 'bg-[#eef1f6] text-[#56657d]'
}

function ringkasMeta(meta) {
  if (!meta) return ''
  const bagian = []
  if (Array.isArray(meta.field) && meta.field.length > 0) bagian.push(`field: ${meta.field.join(', ')}`)
  if (meta.kode) bagian.push(`kode: ${meta.kode}`)
  if (meta.metode) bagian.push(`metode: ${meta.metode}`)
  if (meta.oleh) bagian.push(`oleh: ${meta.oleh}`)
  if (meta.nilai !== undefined && meta.nilai !== null) bagian.push(`nilai: ${meta.nilai}`)
  return bagian.join(' · ')
}
</script>

<template>
  <AdminPageHeader
    judul="Log Aktivitas & Analisis Evaluasi Soal"
    deskripsi="Audit aktivitas belajar siswa, perbandingan nilai percobaan (1x vs 2x), persentase kemunculan soal, serta 4 Tes Klasik (Reliabilitas, Validitas, Daya Pembeda, Kesukaran)."
    :galat="galat"
    @coba-lagi="muatUlangAktif"
  />

  <!-- Navigasi Tab -->
  <nav class="mb-5 flex flex-wrap gap-2 rounded-2xl border border-[#e1e7f0] bg-white p-2" aria-label="Tab analisis aktivitas">
    <button
      v-for="t in daftarTab"
      :key="t.id"
      type="button"
      class="rounded-xl px-4 py-2.5 text-[12px] font-bold transition-colors"
      :class="
        tabAktif === t.id
          ? 'bg-[#0b203d] text-[#ffc01d]'
          : 'text-[#4b5b75] hover:bg-[#f2f6fc] hover:text-[#17253f]'
      "
      @click="tabAktif = t.id"
    >
      {{ t.label }}
    </button>
  </nav>

  <!-- ===================================================================== -->
  <!-- TAB 1: LOG AKTIVITAS USER                                             -->
  <!-- ===================================================================== -->
  <template v-if="tabAktif === 'log'">
    <section class="mb-5 grid gap-3 rounded-2xl border border-[#e1e7f0] bg-white p-4 md:grid-cols-4">
      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        User
        <Select
          v-model="siswaId"
          :options="pilihanSiswa"
          option-label="label"
          option-value="value"
          placeholder="Pilih user"
          filter
          :loading="memuatSiswa"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Dari
        <input v-model="dari" type="date" class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]" />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Sampai
        <input v-model="sampai" type="date" class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]" />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Kategori
        <Select
          v-model="kategori"
          :options="pilihanKategori"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>
    </section>

    <p v-if="memuat" class="py-10 text-center text-[13px] text-[#65748c]">Memuat aktivitas…</p>

    <p v-else-if="baris.length === 0" class="rounded-2xl border border-[#e1e7f0] bg-white py-12 text-center text-[13px] text-[#65748c]">
      Tidak ada aktivitas pada rentang tanggal ini.
    </p>

    <section v-else class="overflow-hidden rounded-2xl border border-[#e1e7f0] bg-white">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] table-fixed text-left">
          <thead class="bg-[#f5f8fc] text-[10px] uppercase tracking-wider text-[#65748c]">
            <tr>
              <th class="w-[22%] px-4 py-3 font-bold">Nama User</th>
              <th class="w-[11%] px-4 py-3 font-bold">Kategori</th>
              <th class="px-4 py-3 font-bold">Kegiatan User</th>
              <th class="w-[15%] px-4 py-3 font-bold">Waktu</th>
              <th class="w-[9%] px-4 py-3 text-right font-bold">Aksi</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-[#f1f4f9] text-[13px]">
            <template
              v-for="(b, i) in baris"
              :key="`${b.user_id ?? 'x'}-${b.tanggal}-${b.aksi}-${i}`"
            >
              <tr class="align-top transition-colors hover:bg-[#fafcff]">
                <td class="px-4 py-3">
                  <p class="font-semibold text-[#172033]">{{ b.user_nama || 'Tanpa akun' }}</p>
                  <p v-if="b.user_email" class="mt-0.5 truncate text-[11px] text-[#98a5b6]">{{ b.user_email }}</p>
                </td>

                <td class="px-4 py-3">
                  <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', warnaKategori(b.kategori)]">
                    {{ labelKategori(b.kategori) }}
                  </span>
                </td>

                <td class="px-4 py-3">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-[#172033]">{{ b.label }}</span>
                    <span
                      v-if="b.kali > 1"
                      class="rounded-full bg-[#eef1f6] px-2 py-0.5 text-[10px] font-bold text-[#56657d]"
                    >
                      ×{{ b.kali }}
                    </span>
                  </div>
                  <p v-if="b.objek" class="mt-0.5 text-[12px] leading-snug text-[#56657d]">
                    {{ b.objek }}
                  </p>
                </td>

                <td class="px-4 py-3 text-[#56657d]">
                  <p class="whitespace-nowrap">{{ tanggalPendek(b.tanggal) }}</p>
                  <p class="mt-0.5 text-[11px] tabular-nums text-[#98a5b6]">{{ jam(b.pada) }}</p>
                </td>

                <td class="px-4 py-3 text-right">
                  <button
                    v-if="perluDetail(b)"
                    type="button"
                    class="rounded-lg border border-[#dbe3ee] px-2 py-1 text-[11px] font-semibold text-[#3d4b63] hover:bg-[#f5f8fc]"
                    @click="buka(b.tanggal, b)"
                  >
                    {{ membuka === kunci(b.tanggal, b) ? 'Tutup' : 'Rincian' }}
                  </button>
                </td>
              </tr>

              <tr v-if="membuka === kunci(b.tanggal, b)" class="bg-[#fbfcfe]">
                <td colspan="5" class="px-4 pb-3 pt-0">
                  <ul class="space-y-1 border-l-2 border-[#dbe3ee] pl-4">
                    <li
                      v-for="(r, j) in (detail[kunci(b.tanggal, b)] || [])"
                      :key="j"
                      class="text-[12px] text-[#56657d]"
                    >
                      <span class="font-semibold tabular-nums text-[#7a8798]">{{ jam(r.pada) }}</span>
                      <span v-if="r.user_nama" class="ml-2 text-[#3d4b63]">{{ r.user_nama }}</span>
                      <span v-if="ringkasMeta(r.meta)" class="ml-2">{{ ringkasMeta(r.meta) }}</span>
                      <span v-if="r.ip" class="ml-2 text-[#98a5b6]">IP {{ r.ip }}</span>
                    </li>
                    <li
                      v-if="(detail[kunci(b.tanggal, b)] || []).length === 0"
                      class="text-[12px] text-[#98a5b6]"
                    >
                      Tidak ada rincian tersimpan.
                    </li>
                  </ul>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
  </template>

  <!-- ===================================================================== -->
  <!-- TAB 2: ANALISIS PERCOBAAN (NYOBA 1X VS NYOBA 2X+)                     -->
  <!-- ===================================================================== -->
  <template v-else-if="tabAktif === 'percobaan'">
    <section class="mb-5 grid gap-3 rounded-2xl border border-[#e1e7f0] bg-white p-4 md:grid-cols-3">
      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Tingkat Seleksi
        <Select
          v-model="filterTingkatId"
          :options="pilihanTingkat"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Jenis Asesmen
        <Select
          v-model="filterPeruntukan"
          :options="pilihanPeruntukan"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Filter Siswa
        <Select
          v-model="siswaId"
          :options="pilihanSiswa"
          option-label="label"
          option-value="value"
          filter
          class="w-full"
        />
      </label>
    </section>

    <p v-if="memuatAnalisis" class="py-10 text-center text-[13px] text-[#65748c]">Menghitung analisis percobaan…</p>

    <template v-else-if="ringkasanPercobaan">
      <!-- Kartu KPI Percobaan -->
      <section class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#66748c]">Rata-rata Percobaan Ke-1</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#172033]">{{ ringkasanPercobaan.rata_nilai_percobaan_1 }}</p>
          <p class="mt-1 text-[11px] text-[#64748b]">
            {{ ringkasanPercobaan.jumlah_coba_sekali }} paket hanya dicoba 1x
          </p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#0759a5]">Rata-rata Percobaan Ke-2</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#0759a5]">{{ ringkasanPercobaan.rata_nilai_percobaan_2 }}</p>
          <p class="mt-1 text-[11px] text-[#64748b]">
            {{ ringkasanPercobaan.jumlah_coba_dua_kali }} paket dicoba tepat 2x
          </p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#1d7a42]">Rata-rata Percobaan Ke-3+</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#1d7a42]">{{ ringkasanPercobaan.rata_nilai_percobaan_3_plus }}</p>
          <p class="mt-1 text-[11px] text-[#64748b]">
            {{ ringkasanPercobaan.jumlah_coba_tiga_plus }} paket dicoba ≥ 3x
          </p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#8a6100]">Peningkatan Nilai (1x → 2x)</p>
          <p
            class="mt-2 text-[28px] font-extrabold"
            :class="ringkasanPercobaan.rata_peningkatan_1_ke_2 >= 0 ? 'text-[#159564]' : 'text-[#b42318]'"
          >
            {{ ringkasanPercobaan.rata_peningkatan_1_ke_2 >= 0 ? '+' : '' }}{{ ringkasanPercobaan.rata_peningkatan_1_ke_2 }}
          </p>
          <p class="mt-1 text-[11px] font-medium text-[#159564]">
            {{ ringkasanPercobaan.persen_siswa_meningkat }}% siswa mengalami kenaikan skor
          </p>
        </article>
      </section>

      <!-- Visualisasi Bar Perbandingan Kurva Belajar -->
      <section class="mb-5 rounded-2xl border border-[#e0e7f2] bg-white p-5">
        <h2 class="text-[14px] font-bold text-[#14233f]">Kurva Belajar: Perbandingan Nilai Nyoba 1 Kali vs 2 Kali vs 3+ Kali</h2>
        <p class="mt-1 text-[12px] text-[#65748c]">
          Memperlihatkan perkembangan pemahaman siswa setelah membaca pembahasan dan mengulang latihan/simulasi.
        </p>

        <div class="mt-4 space-y-3">
          <div class="grid grid-cols-[150px_1fr_65px] items-center gap-3 text-[12px]">
            <span class="font-semibold text-[#3d4b63]">Percobaan 1 (Nyoba 1x)</span>
            <div class="h-3 overflow-hidden rounded-full bg-[#edf2fa]">
              <div
                class="h-full rounded-full bg-[#64748b]"
                :style="{ width: Math.min(100, ringkasanPercobaan.rata_nilai_percobaan_1) + '%' }"
              ></div>
            </div>
            <strong class="text-right tabular-nums">{{ ringkasanPercobaan.rata_nilai_percobaan_1 }}</strong>
          </div>

          <div class="grid grid-cols-[150px_1fr_65px] items-center gap-3 text-[12px]">
            <span class="font-semibold text-[#0759a5]">Percobaan 2 (Nyoba 2x)</span>
            <div class="h-3 overflow-hidden rounded-full bg-[#edf2fa]">
              <div
                class="h-full rounded-full bg-[#2563eb]"
                :style="{ width: Math.min(100, ringkasanPercobaan.rata_nilai_percobaan_2) + '%' }"
              ></div>
            </div>
            <strong class="text-right tabular-nums text-[#0759a5]">{{ ringkasanPercobaan.rata_nilai_percobaan_2 }}</strong>
          </div>

          <div class="grid grid-cols-[150px_1fr_65px] items-center gap-3 text-[12px]">
            <span class="font-semibold text-[#1d7a42]">Percobaan 3+ (Nyoba ≥3x)</span>
            <div class="h-3 overflow-hidden rounded-full bg-[#edf2fa]">
              <div
                class="h-full rounded-full bg-[#16a34a]"
                :style="{ width: Math.min(100, ringkasanPercobaan.rata_nilai_percobaan_3_plus) + '%' }"
              ></div>
            </div>
            <strong class="text-right tabular-nums text-[#1d7a42]">{{ ringkasanPercobaan.rata_nilai_percobaan_3_plus }}</strong>
          </div>
        </div>
      </section>

      <!-- Tabel Rincian Nilai per Percobaan -->
      <section class="overflow-hidden rounded-2xl border border-[#e1e7f0] bg-white">
        <div class="border-b border-[#e6edf6] px-5 py-4">
          <h3 class="text-[14px] font-bold text-[#14233f]">Rincian Nilai Percobaan Siswa (Nyoba 1x, 2x, 3x+)</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[860px] text-left text-[13px]">
            <thead class="bg-[#f5f8fc] text-[10px] uppercase tracking-wider text-[#65748c]">
              <tr>
                <th class="px-4 py-3 font-bold">Nama Siswa</th>
                <th class="px-4 py-3 font-bold">Jenis & Paket Tes</th>
                <th class="px-4 py-3 text-center font-bold">Kali Coba</th>
                <th class="px-4 py-3 text-right font-bold">Nilai Coba 1x</th>
                <th class="px-4 py-3 text-right font-bold">Nilai Coba 2x</th>
                <th class="px-4 py-3 text-right font-bold">Nilai Coba 3x+</th>
                <th class="px-4 py-3 text-right font-bold">Selisih (1→2)</th>
                <th class="px-4 py-3 font-bold">Urutan Riwayat Nilai</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#f1f4f9]">
              <tr v-for="(item, idx) in rincianPercobaan" :key="idx" class="hover:bg-[#fafcff]">
                <td class="px-4 py-3 font-semibold text-[#172033]">{{ item.user_nama }}</td>
                <td class="px-4 py-3">
                  <span :class="['mr-2 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', warnaJenisTes(item.jenis)]">
                    {{ item.jenis }}
                  </span>
                  <span class="font-medium text-[#273b59]">{{ item.target_nama }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="rounded-full bg-[#eef1f6] px-2.5 py-0.5 text-[11px] font-bold text-[#172033]">
                    {{ item.jumlah_percobaan }}×
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ item.nilai_percobaan_1 }}</td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums text-[#0759a5]">
                  {{ item.nilai_percobaan_2 != null ? item.nilai_percobaan_2 : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums text-[#1d7a42]">
                  {{ item.nilai_percobaan_3_plus != null ? item.nilai_percobaan_3_plus : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums">
                  <span
                    v-if="item.selisih_1_ke_2 != null"
                    :class="item.selisih_1_ke_2 >= 0 ? 'text-[#159564]' : 'text-[#b42318]'"
                  >
                    {{ item.selisih_1_ke_2 >= 0 ? '+' : '' }}{{ item.selisih_1_ke_2 }}
                  </span>
                  <span v-else class="text-[#98a5b6]">—</span>
                </td>
                <td class="px-4 py-3 text-[12px] tabular-nums text-[#56657d]">
                  {{ item.riwayat_nilai.join(' → ') }}
                </td>
              </tr>
              <tr v-if="rincianPercobaan.length === 0">
                <td colspan="8" class="py-10 text-center text-[#65748c]">Belum ada data pengerjaan siswa.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </template>

  <!-- ===================================================================== -->
  <!-- TAB 3: PERSENTASE KALI KELUAR SOAL (ITEM EXPOSURE & BOBOT PELUANG)    -->
  <!-- ===================================================================== -->
  <template v-else-if="tabAktif === 'exposure'">
    <section class="mb-5 grid gap-3 rounded-2xl border border-[#e1e7f0] bg-white p-4 md:grid-cols-4">
      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Tingkat Seleksi
        <Select
          v-model="filterTingkatId"
          :options="pilihanTingkat"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Peruntukan Soal
        <Select
          v-model="filterPeruntukan"
          :options="pilihanPeruntukan"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Status Kemunculan
        <Select
          v-model="filterStatusExposure"
          :options="pilihanStatusExposure"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Cari Kode / Soal / Materi
        <input
          v-model="cariSoal"
          type="text"
          placeholder="Ketik kode atau kata kunci…"
          class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]"
        />
      </label>
    </section>

    <p v-if="memuatAnalisis" class="py-10 text-center text-[13px] text-[#65748c]">Memuat distribusi kemunculan soal…</p>

    <template v-else>
      <section class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#66748c]">Total Bank Soal</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#172033]">{{ ringkasanExposure.total }}</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Sesuai filter aktif</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#0759a5]">Sudah Pernah Keluar</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#0759a5]">
            {{ ringkasanExposure.sudahKeluar }}
            <span class="text-[14px] font-semibold text-[#64748b]">({{ ringkasanExposure.persenSudah }}%)</span>
          </p>
          <p class="mt-1 text-[11px] text-[#64748b]">Peluang keluar berikutnya otomatis diperkecil</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#1d7a42]">Belum Pernah Keluar (0x)</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#1d7a42]">{{ ringkasanExposure.belumKeluar }}</p>
          <p class="mt-1 text-[11px] font-medium text-[#159564]">Prioritas tertinggi (Bobot W = 1.0)</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#8a6100]">Rata-rata Kali Keluar</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#172033]">{{ ringkasanExposure.rataKeluar }}×</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Dari {{ reliabilitas?.jumlah_sesi ?? 0 }} sesi pengerjaan</p>
        </article>
      </section>

      <section class="mb-5 rounded-2xl border border-[#dbe7fb] bg-[#f4f8ff] p-4 text-[12px] text-[#1e3a6d]">
        <strong>Aturan Penyeimbangan Kemunculan Soal (Inverse Exposure Weighting):</strong>
        Setiap butir soal diberi bobot peluang keluar <code>W = 1 / (kali_keluar + 1)</code>.
        Soal yang <strong>belum pernah keluar (0×)</strong> mendapat bobot penuh <code>1.0000</code>, sedangkan soal yang
        <strong>sudah keluar</strong> otomatis memiliki persentase peluang keluar berikutnya yang lebih kecil
        (misal keluar 1× → bobot <code>0.5000</code>, keluar 4× → bobot <code>0.2000</code>).
      </section>

      <section class="overflow-hidden rounded-2xl border border-[#e1e7f0] bg-white">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[960px] text-left text-[13px]">
            <thead class="bg-[#f5f8fc] text-[10px] uppercase tracking-wider text-[#65748c]">
              <tr>
                <th class="px-4 py-3 font-bold">Kode & Soal</th>
                <th class="px-4 py-3 font-bold">Materi & Level</th>
                <th class="px-4 py-3 text-center font-bold">Kali Keluar</th>
                <th class="px-4 py-3 text-right font-bold">% Kemunculan Sesi</th>
                <th class="px-4 py-3 text-right font-bold">Bobot (W)</th>
                <th class="px-4 py-3 font-bold">% Peluang Keluar Berikutnya</th>
                <th class="px-4 py-3 text-right font-bold">% Dijawab Benar</th>
                <th class="px-4 py-3 font-bold">Evaluasi Soal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#f1f4f9]">
              <tr v-for="b in butirExposureTersaring.slice(0, 120)" :key="b.soal_id" class="hover:bg-[#fafcff]">
                <td class="max-w-[280px] px-4 py-3">
                  <p class="font-bold text-[#172033]">{{ b.kode_soal }}</p>
                  <p class="mt-0.5 truncate text-[12px] text-[#56657d]" :title="b.pertanyaan_ringkas">
                    {{ b.pertanyaan_ringkas }}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-[#172033]">{{ b.materi_judul }}</p>
                  <span class="mt-0.5 inline-block rounded bg-[#eef1f6] px-1.5 py-0.5 text-[10px] font-bold uppercase text-[#56657d]">
                    {{ b.peruntukan }} · {{ b.level }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-bold tabular-nums">
                  <span
                    class="rounded-full px-2.5 py-0.5 text-[11px]"
                    :class="b.jumlah_keluar === 0 ? 'bg-[#e9f7ee] text-[#1d7a42]' : 'bg-[#e8f0fb] text-[#0759a5]'"
                  >
                    {{ b.jumlah_keluar }}×
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ b.persentase_keluar }}%</td>
                <td class="px-4 py-3 text-right font-mono text-[12px] text-[#56657d]">{{ b.bobot_peluang }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-[#edf2fa]">
                      <div
                        class="h-full rounded-full"
                        :class="b.jumlah_keluar === 0 ? 'bg-[#16a34a]' : 'bg-[#3b82f6]'"
                        :style="{ width: Math.min(100, b.peluang_keluar_berikutnya * 4) + '%' }"
                      ></div>
                    </div>
                    <span class="font-bold tabular-nums text-[#172033]">{{ b.peluang_keluar_berikutnya }}%</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums">
                  <span v-if="b.jumlah_menjawab > 0">{{ Math.round(b.tingkat_kesukaran * 100) }}% ({{ b.jumlah_benar }}/{{ b.jumlah_menjawab }})</span>
                  <span v-else class="text-[#98a5b6]">—</span>
                </td>
                <td class="px-4 py-3">
                  <span :class="['rounded-full px-2.5 py-1 text-[10px] font-bold', warnaRekomendasi(b.rekomendasi)]">
                    {{ b.rekomendasi }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </template>

  <!-- ===================================================================== -->
  <!-- TAB 4: 4 TES KLASIK SOAL (CTT: RELIABILITAS, VALIDITAS, DAYA BEDA, P) -->
  <!-- ===================================================================== -->
  <template v-else-if="tabAktif === 'klasik'">
    <section class="mb-5 grid gap-3 rounded-2xl border border-[#e1e7f0] bg-white p-4 md:grid-cols-4">
      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Tingkat Seleksi
        <Select
          v-model="filterTingkatId"
          :options="pilihanTingkat"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Peruntukan Soal
        <Select
          v-model="filterPeruntukan"
          :options="pilihanPeruntukan"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Status Kualitas Butir
        <Select
          v-model="filterKualitas"
          :options="pilihanKualitas"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </label>

      <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
        Cari Kode / Soal / Materi
        <input
          v-model="cariSoal"
          type="text"
          placeholder="Ketik kode atau kata kunci…"
          class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]"
        />
      </label>
    </section>

    <p v-if="memuatAnalisis" class="py-10 text-center text-[13px] text-[#65748c]">Menghitung 4 parameter Teori Tes Klasik (CTT)…</p>

    <template v-else-if="reliabilitas">
      <!-- 4 Kartu Indikator Utama Tes Klasik -->
      <section class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#0759a5]">1. Reliabilitas Tes (KR-20)</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#0759a5]">{{ reliabilitas.kr20 }}</p>
          <p class="mt-1 text-[11px] font-semibold text-[#159564]">{{ reliabilitas.kategori }}</p>
          <p class="mt-0.5 text-[11px] text-[#64748b]">Dari {{ reliabilitas.jumlah_sesi }} sesi pengerjaan</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#1d7a42]">2. Validitas Butir (r_pbis)</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#1d7a42]">{{ reliabilitas.rata_validitas }}</p>
          <p class="mt-1 text-[11px] font-semibold text-[#159564]">
            {{ reliabilitas.jumlah_soal_valid }} dari {{ reliabilitas.jumlah_soal_teruji }} soal teruji valid
          </p>
          <p class="mt-0.5 text-[11px] text-[#64748b]">Korelasi Point-Biserial (r ≥ 0.20)</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#8a6100]">3. Daya Pembeda (D)</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#172033]">{{ reliabilitas.rata_daya_pembeda }}</p>
          <p class="mt-1 text-[11px] font-semibold text-[#3d4b63]">
            {{ reliabilitas.jumlah_soal_baik }} soal berdaya beda baik
          </p>
          <p class="mt-0.5 text-[11px] text-[#64748b]">Metode 27% Kelompok Atas vs Bawah</p>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <p class="text-[11px] font-bold uppercase tracking-wider text-[#66748c]">4. Tingkat Kesukaran (P)</p>
          <p class="mt-2 text-[28px] font-extrabold text-[#172033]">{{ reliabilitas.rata_kesukaran }}</p>
          <p class="mt-1 text-[11px] font-semibold text-[#3d4b63]">
            Rentang ideal: 0.30 ≤ P ≤ 0.70
          </p>
          <p class="mt-0.5 text-[11px] text-[#64748b]">Proporsi peserta menjawab benar</p>
        </article>
      </section>

      <!-- Tabel 4 Tes Klasik per Butir Soal -->
      <section class="overflow-hidden rounded-2xl border border-[#e1e7f0] bg-white">
        <div class="border-b border-[#e6edf6] px-5 py-4">
          <h3 class="text-[14px] font-bold text-[#14233f]">Tabel Evaluasi 4 Tes Klasik per Butir Soal</h3>
          <p class="mt-0.5 text-[12px] text-[#65748c]">
            Menampilkan Tingkat Kesukaran (P), Daya Pembeda (D), Validitas Point-Biserial (r_pbis), serta rekomendasi kualitas butir soal.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[980px] text-left text-[13px]">
            <thead class="bg-[#f5f8fc] text-[10px] uppercase tracking-wider text-[#65748c]">
              <tr>
                <th class="px-4 py-3 font-bold">Kode & Pertanyaan Soal</th>
                <th class="px-4 py-3 font-bold">Materi</th>
                <th class="px-4 py-3 text-center font-bold">Sampel (Benar/N)</th>
                <th class="px-4 py-3 font-bold">1. Tingkat Kesukaran (P)</th>
                <th class="px-4 py-3 font-bold">2. Daya Pembeda (D)</th>
                <th class="px-4 py-3 font-bold">3. Validitas (r_pbis)</th>
                <th class="px-4 py-3 font-bold">4. Kesimpulan / Mutu Soal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#f1f4f9]">
              <tr v-for="b in butirKlasikTersaring.slice(0, 120)" :key="b.soal_id" class="hover:bg-[#fafcff]">
                <td class="max-w-[260px] px-4 py-3">
                  <p class="font-bold text-[#172033]">{{ b.kode_soal }}</p>
                  <p class="mt-0.5 truncate text-[12px] text-[#56657d]" :title="b.pertanyaan_ringkas">
                    {{ b.pertanyaan_ringkas }}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-[#172033]">{{ b.materi_judul }}</p>
                  <span class="mt-0.5 inline-block rounded bg-[#eef1f6] px-1.5 py-0.5 text-[10px] font-bold uppercase text-[#56657d]">
                    {{ b.peruntukan }} · {{ b.level }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-semibold tabular-nums">
                  {{ b.jumlah_benar }} / {{ b.jumlah_menjawab }}
                </td>
                <td class="px-4 py-3">
                  <p class="font-bold tabular-nums text-[#172033]">P = {{ b.tingkat_kesukaran }}</p>
                  <span :class="['mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold', warnaKesukaran(b.kategori_kesukaran)]">
                    {{ b.kategori_kesukaran }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <p class="font-bold tabular-nums text-[#172033]">D = {{ b.daya_pembeda }}</p>
                  <span :class="['mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold', warnaDayaBeda(b.kategori_daya_pembeda)]">
                    {{ b.kategori_daya_pembeda }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <p class="font-bold tabular-nums text-[#172033]">r = {{ b.validitas_rpbis }}</p>
                  <span
                    class="mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold"
                    :class="b.valid ? 'bg-[#e9f7ee] text-[#1d7a42]' : 'bg-[#fdecec] text-[#b42318]'"
                  >
                    {{ b.kategori_validitas }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', warnaRekomendasi(b.rekomendasi)]">
                    {{ b.rekomendasi }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </template>
</template>
