<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import { useAdminStore } from '@/stores/admin.js'
import { adminService } from '@/services/admin.js'
import { useAuthStore } from '@/stores/auth.js'
import { pesanError } from '@/lib/errors.js'
import { kategoriAktivitas, kategoriReferal } from '@/services/mappers/admin.js'

// Dashboard admin (Fase 6 A1). Angka ringkasan selalu seluruh waktu; filter
// tanggal hanya memengaruhi Analitik Pengguna (GET /admin/dashboard?dari=&sampai=).
// Laporan kecukupan bank soal diambil terpisah karena butuh tingkat_id.

const auth = useAuthStore()
const router = useRouter()
const admin = useAdminStore()

const tingkatId = ref(null)
const tingkatList = ref([])
const galatKecukupan = ref('')
const galatRingkasan = ref('')
const galatPeriode = ref('')
const galatAnalitik = ref('')

// Draf input tanggal dan periode yang sudah diterapkan. Draf hanya dikirim ke API saat "Terapkan".
const dariDraf = ref('')
const sampaiDraf = ref('')
const periodeTerapan = ref({ dari: '', sampai: '' })

// Kelompok dan jenis diagram hanya mengubah tampilan dari data yang sudah ada, tanpa request baru.
const kelompok = ref('aktivitas')
const jenisDiagram = ref('batang-vertikal')
const pilihanKelompok = [
  { id: 'aktivitas', label: 'Aktivitas ujian' },
  { id: 'referal', label: 'Kode referal' },
]

const r = computed(() => admin.ringkasan)
const k = computed(() => admin.kecukupan)
const aktivitasTotal = computed(() => (r.value?.pengerjaanPerJenis ?? []).reduce((total, x) => total + x.jumlah, 0))
const simulasiNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'simulasi')?.nilai ?? null)
const pretestNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'pretest')?.nilai ?? null)
const latihanNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'latihan')?.nilai ?? null)
const jumlahSiswaTingkat = computed(() => (r.value?.siswaPerTingkat ?? []).reduce((total, item) => total + item.jumlahSiswa, 0))

const analitik = computed(() => r.value?.analitikPengguna ?? null)
const jumlahPendaftar = computed(() => analitik.value?.siswaTerdaftar ?? null)
const kategori = computed(() => (kelompok.value === 'referal' ? kategoriReferal(analitik.value) : kategoriAktivitas(analitik.value)))

// Donat hanya untuk referal (kategorinya saling lepas). Aktivitas tidak memakai pie.
const jenisEfektif = computed(() => (jenisDiagram.value === 'donat' && kelompok.value !== 'referal' ? 'batang-vertikal' : jenisDiagram.value))
const pilihanJenis = computed(() => [
  { id: 'batang-vertikal', label: 'Batang vertikal' },
  { id: 'batang-horizontal', label: 'Batang horizontal' },
  ...(kelompok.value === 'referal' ? [{ id: 'donat', label: 'Donat' }] : []),
])
const chartType = computed(() => (jenisEfektif.value === 'donat' ? 'doughnut' : 'bar'))
const chartKey = computed(() => `${kelompok.value}-${jenisEfektif.value}`)

const pesanGrafik = computed(() => {
  if (!kategori.value.length) return 'Grafik belum tersedia: data analitik belum dikirim server.'
  if (kelompok.value === 'referal' && jumlahPendaftar.value === 0) return 'Belum ada siswa terdaftar pada periode ini.'
  if (jenisEfektif.value === 'donat' && kategori.value.some((item) => item.jumlah == null)) {
    return 'Diagram donat membutuhkan kedua angka. Pilih batang untuk melihat angka yang tersedia.'
  }
  if (kategori.value.every((item) => item.jumlah == null)) return 'Grafik belum tersedia: angka kategori ini belum dikirim server.'
  return ''
})

const warnaAktivitas = ['#8fb0e6', '#244f96', '#8fb0e6', '#244f96', '#8fb0e6', '#244f96']
const warnaReferal = ['#244f96', '#b47b00']
const dataGrafik = computed(() => ({
  labels: kategori.value.map((item) => item.label),
  datasets: [{
    label: kelompok.value === 'referal' ? 'Jumlah siswa' : 'Jumlah siswa unik',
    data: kategori.value.map((item) => item.jumlah ?? null),
    backgroundColor: kelompok.value === 'referal' ? warnaReferal : warnaAktivitas,
    borderRadius: 5,
    maxBarThickness: 60,
  }],
}))
const opsiGrafik = computed(() => {
  if (jenisEfektif.value === 'donat') {
    return { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
  }
  return {
    indexAxis: jenisEfektif.value === 'batang-horizontal' ? 'y' : 'x',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { precision: 0, color: '#344866' } },
      y: { beginAtZero: true, ticks: { precision: 0, color: '#344866' } },
    },
  }
})

// Persentase hanya untuk referal: kedua kategori saling lepas dan membagi pendaftar.
const tabelBaris = computed(() => kategori.value.map((item) => ({
  ...item,
  persen: kelompok.value === 'referal' && item.jumlah != null && jumlahPendaftar.value > 0
    ? Math.round((item.jumlah / jumlahPendaftar.value) * 100)
    : null,
})))
const judulTabel = computed(() => (kelompok.value === 'referal'
  ? 'Siswa terdaftar dengan dan tanpa kode referal'
  : 'Jumlah siswa unik yang mulai dan selesai tiap ujian'))

const tanggalIndonesia = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
const labelPeriode = computed(() => {
  const p = admin.ringkasanPeriode
  if (!p?.dari && !p?.sampai) return 'Seluruh waktu'
  if (p.dari && p.sampai) return `${tanggalIndonesia(p.dari)} sampai ${tanggalIndonesia(p.sampai)}`
  return p.dari ? `mulai ${tanggalIndonesia(p.dari)}` : `sampai ${tanggalIndonesia(p.sampai)}`
})

const kurangBank = computed(() => admin.bankSoalRingkas?.kurang ?? null)
const materiButuhPerhatian = computed(() => (k.value?.latihanPerMateri ?? []).filter((x) => x.kurang || !x.punyaLatihan).slice(0, 4))

function muatRingkasanView() {
  galatRingkasan.value = ''
  galatAnalitik.value = ''
  return admin.muatRingkasan({
    dari: periodeTerapan.value.dari || undefined,
    sampai: periodeTerapan.value.sampai || undefined,
  }).catch((err) => {
    galatAnalitik.value = pesanError(err, 'Analitik pengguna gagal dimuat.')
    // Header hanya untuk kegagalan yang meniadakan seluruh ringkasan; kegagalan filter cukup di bagian analitik.
    if (!admin.ringkasan) galatRingkasan.value = pesanError(err, 'Gagal memuat ringkasan admin.')
  })
}

function terapkanPeriode() {
  galatPeriode.value = ''
  if (dariDraf.value && sampaiDraf.value && dariDraf.value > sampaiDraf.value) {
    galatPeriode.value = 'Tanggal awal tidak boleh setelah tanggal akhir.'
    return
  }
  periodeTerapan.value = { dari: dariDraf.value, sampai: sampaiDraf.value }
  void muatRingkasanView()
}

function resetPeriode() {
  dariDraf.value = ''
  sampaiDraf.value = ''
  galatPeriode.value = ''
  periodeTerapan.value = { dari: '', sampai: '' }
  void muatRingkasanView()
}

async function muatSemuanya() {
  galatKecukupan.value = ''
  await muatRingkasanView()
  try {
    tingkatList.value = await adminService.daftarTingkatAdmin()
    if (!tingkatId.value && tingkatList.value.length) tingkatId.value = tingkatList.value[0].id
    if (tingkatId.value) await admin.muatKecukupan({ tingkatId: tingkatId.value, force: true })
  } catch (err) {
    galatKecukupan.value = pesanError(err, 'Gagal memuat laporan bank soal.')
  }
}

function gantiTingkat(id) {
  tingkatId.value = Number(id)
  admin
    .muatKecukupan({ tingkatId: tingkatId.value, force: true })
    .catch((err) => {
      galatKecukupan.value = pesanError(err, 'Gagal memuat laporan bank soal.')
    })
}

async function keluar() {
  await auth.logout()
  router.push({ name: 'login' })
}

function formatNilai(n) {
  return n == null ? '—' : String(Math.round(n))
}

onMounted(muatSemuanya)
</script>

<template>
  <div>
    <AdminPageHeader
      judul="Dashboard Super Admin"
      deskripsi="Ringkasan sistem, aktivitas siswa, dan status data SIAP OSN"
      :galat="galatRingkasan"
      @coba-lagi="muatSemuanya"
    >
      <template #aksi-extra>
        <Button label="Keluar" size="small" severity="secondary" outlined @click="keluar" />
      </template>
    </AdminPageHeader>

    <div v-if="admin.loading && !r" class="grid gap-4 md:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-[136px] animate-pulse rounded-2xl border border-[#e0e7f2] bg-white p-5">
        <div class="mb-5 h-8 w-8 rounded-lg bg-[#edf2fa]"></div>
        <div class="h-5 w-2/3 rounded bg-[#edf2fa]"></div>
        <div class="mt-2 h-3 w-1/2 rounded bg-[#edf2fa]"></div>
      </div>
    </div>

    <template v-if="r">
      <p class="mb-3 text-[11px] text-[#71809a]">Angka pada kartu ini selalu seluruh waktu. Filter tanggal hanya berlaku untuk Analitik Pengguna.</p>
      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#e7efff]"></div>
          <p class="text-[25px] font-bold leading-none">{{ r.siswaAktif }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Total Siswa Aktif</p>
          <p class="mt-1 text-[11px] font-medium text-[#159564]">Dari data akun aktif</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#e0f5e8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ aktivitasTotal }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Total Pengerjaan Tercatat</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Akumulasi seluruh jenis asesmen</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#fff3d8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ simulasiNilai == null ? '—' : formatNilai(simulasiNilai) }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Rata-rata Skor Simulasi</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Berdasarkan hasil yang tersedia</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#edf1f8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ kurangBank == null ? '—' : kurangBank }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Bagian Bank Soal Perlu Dilengkapi</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Pada tingkat yang dipilih</p>
        </article>
      </section>
    </template>

    <section class="mt-5 rounded-2xl border border-[#e0e7f2] bg-white p-5" aria-labelledby="judul-analitik-pengguna">
      <h2 id="judul-analitik-pengguna" class="text-[14px] font-bold">Analitik Pengguna</h2>
      <p class="mt-1 text-[11px] text-[#71809a]">
        Tanggal pada filter ini hanya berlaku untuk bagian Analitik Pengguna. Kosongkan tanggal untuk melihat seluruh waktu.
      </p>

      <form class="mt-4 flex flex-wrap items-end gap-3" novalidate @submit.prevent="terapkanPeriode">
        <div class="flex flex-col gap-1">
          <label for="periode-dari" class="text-[11px] font-semibold text-[#344866]">Dari tanggal</label>
          <input id="periode-dari" v-model="dariDraf" type="date" :max="sampaiDraf || undefined"
            class="rounded-lg border border-[#c9d4e5] bg-white px-2 py-1.5 text-[12px]" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="periode-sampai" class="text-[11px] font-semibold text-[#344866]">Sampai tanggal</label>
          <input id="periode-sampai" v-model="sampaiDraf" type="date" :min="dariDraf || undefined"
            class="rounded-lg border border-[#c9d4e5] bg-white px-2 py-1.5 text-[12px]" />
        </div>
        <Button type="submit" label="Terapkan" size="small" />
        <Button type="button" label="Atur ulang" size="small" severity="secondary" outlined @click="resetPeriode" />
      </form>
      <p v-if="galatPeriode" role="alert" class="mt-2 text-[12px] text-[#a33333]">{{ galatPeriode }}</p>

      <p v-if="admin.ringkasanMemuat" role="status" class="mt-5 text-[12px] text-[#586985]">Memuat analitik untuk periode yang dipilih…</p>
      <div v-else-if="galatAnalitik" role="alert" class="mt-5 rounded-xl border border-[#f1c6c6] bg-[#fff6f6] p-4 text-[12px] text-[#a33333]">
        <p>{{ galatAnalitik }} Angka periode sebelumnya tidak ditampilkan.</p>
        <button type="button" class="mt-3 rounded-lg border border-[#e0b3b3] bg-white px-3 py-1.5 text-[11px] font-semibold text-[#7a2222]" @click="muatRingkasanView">Coba lagi</button>
      </div>
      <p v-else-if="!analitik" class="mt-5 text-[12px] text-[#71809a]">Data analitik pengguna belum tersedia dari server.</p>

      <template v-else>
        <div class="mt-5 flex flex-wrap items-baseline gap-x-6 gap-y-1 text-[12px] text-[#586985]">
          <p>Periode: <b class="text-[#18263a]">{{ labelPeriode }}</b></p>
          <p>Total siswa terdaftar: <b class="text-[#18263a]">{{ jumlahPendaftar ?? 'Tidak tersedia' }}</b></p>
        </div>

        <div class="mt-4 flex flex-wrap gap-4">
          <div class="flex flex-wrap gap-2" role="group" aria-label="Pilih kelompok data">
            <button v-for="p in pilihanKelompok" :key="p.id" type="button" :aria-pressed="kelompok === p.id"
              class="rounded-lg px-3 py-1.5 text-[12px] font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#173e79]"
              :class="kelompok === p.id ? 'bg-[#173e79] text-white' : 'border border-[#c9d4e5] bg-white text-[#173e79]'"
              @click="kelompok = p.id">{{ p.label }}</button>
          </div>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Pilih jenis diagram">
            <button v-for="p in pilihanJenis" :key="p.id" type="button" :aria-pressed="jenisEfektif === p.id"
              class="rounded-lg px-3 py-1.5 text-[12px] font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#173e79]"
              :class="jenisEfektif === p.id ? 'bg-[#173e79] text-white' : 'border border-[#c9d4e5] bg-white text-[#173e79]'"
              @click="jenisDiagram = p.id">{{ p.label }}</button>
          </div>
        </div>

        <div v-if="pesanGrafik === ''" class="mt-5 h-72 min-w-0 sm:h-80" aria-hidden="true">
          <Chart :key="chartKey" :type="chartType" :data="dataGrafik" :options="opsiGrafik" class="h-full" />
        </div>
        <p v-else class="mt-5 rounded-xl bg-[#f7f9fc] p-4 text-[12px] text-[#586985]">{{ pesanGrafik }}</p>

        <div class="mt-5 overflow-x-auto">
          <table class="w-full text-left text-[12px]">
            <caption class="mb-2 text-left text-[12px] font-semibold text-[#344866]">{{ judulTabel }}</caption>
            <thead>
              <tr class="border-b border-[#e1e8f2] text-[#586985]">
                <th scope="col" class="py-2 pr-3 font-semibold">Kategori</th>
                <th scope="col" class="py-2 pr-3 text-right font-semibold">Jumlah siswa</th>
                <th v-if="kelompok === 'referal'" scope="col" class="py-2 text-right font-semibold">Dari pendaftar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in tabelBaris" :key="item.label" class="border-b border-dashed border-[#e1e8f2]">
                <th scope="row" class="py-2 pr-3 font-semibold">{{ item.label }}</th>
                <td class="py-2 pr-3 text-right">{{ item.jumlah ?? 'Tidak tersedia' }}</td>
                <td v-if="kelompok === 'referal'" class="py-2 text-right">{{ item.persen == null ? '—' : `${item.persen}%` }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div class="mt-5 space-y-1.5 rounded-xl bg-[#f7f9fc] p-4 text-[11px] leading-relaxed text-[#64748b]">
        <p><b>Pendaftar</b>: akun siswa yang dibuat dalam periode (tanggal akun dibuat). Batas hari mengikuti zona waktu server (UTC).</p>
        <p><b>Mulai dan selesai</b>: siswa unik yang memulai atau menyelesaikan pre-test, latihan, atau simulasi dengan tanggal kejadian dalam periode. Angka mulai dan selesai dihitung terpisah dan tidak selalu saling mengurangi; tidak dipersentasekan terhadap pendaftar.</p>
        <p><b>Kode referal</b>: status kode saat ini dari akun yang terdaftar dalam periode, bukan tanggal kode diisi. Dengan kode dan tanpa kode saling lepas dan jumlahnya sama dengan siswa terdaftar. Akun yang terdaftar sebelum kolom kode referal ada (sebelum 26 Okt 2026) tidak punya catatan pemberi, sehingga dihitung tanpa kode referal.</p>
        <p>Mencakup siswa aktif dan nonaktif; akun yang dihapus dan akun admin tidak dihitung. Satu siswa dapat masuk beberapa kategori aktivitas.</p>
        <p>Kode referal dapat diisi saat daftar atau setelah masuk melalui Google. Jumlah ini bukan jumlah kunjungan dari link.</p>
        <p>Jumlah klik link referal belum tercatat, sehingga tidak ditampilkan.</p>
      </div>
    </section>

    <template v-if="r">
      <section class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <h2 class="mb-1 text-[14px] font-bold">Distribusi Siswa per Tingkat</h2>
          <p class="mb-4 text-[11px] text-[#71809a]">Seluruh waktu.</p>
          <ul v-if="r.siswaPerTingkat.length" class="space-y-4">
            <li v-for="(t, index) in r.siswaPerTingkat" :key="t.tingkatId" class="grid grid-cols-[100px_minmax(0,1fr)_42px] items-center gap-3 text-[12px]">
              <span class="font-semibold">{{ t.nama }}</span>
              <span class="h-2 overflow-hidden rounded-full bg-[#edf2fa]">
                <span
                  class="block h-full rounded-full"
                  :class="index === 0 ? 'bg-[#548bf1]' : 'bg-[#f3b72d]'"
                  :style="{ width: (jumlahSiswaTingkat ? t.jumlahSiswa / jumlahSiswaTingkat * 100 : 0) + '%' }"
                ></span>
              </span>
              <b class="text-right text-[#586985]">{{ t.jumlahSiswa }}</b>
            </li>
          </ul>
          <p v-else class="py-4 text-[12px] text-[#71809a]">Belum ada data distribusi tingkat.</p>

          <h2 class="mb-1 mt-8 text-[14px] font-bold">Data Hasil Pembelajaran & Simulasi</h2>
          <p class="mb-3 text-[11px] text-[#71809a]">Seluruh waktu.</p>
          <dl class="divide-y divide-dashed divide-[#e1e8f2] text-[12px]">
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata skor pre-test</dt><dd class="font-bold">{{ pretestNilai == null ? '—' : formatNilai(pretestNilai) + '%' }}</dd></div>
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata nilai latihan soal</dt><dd class="font-bold">{{ formatNilai(latihanNilai) }}</dd></div>
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata nilai simulasi</dt><dd class="font-bold">{{ formatNilai(simulasiNilai) }}</dd></div>
          </dl>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[14px] font-bold">Materi Perlu Diperhatikan</h2>
            <label class="sr-only" for="tingkat-bank-admin">Pilih tingkat</label>
            <select id="tingkat-bank-admin" class="max-w-[155px] rounded-lg border border-[#e0e7f2] bg-white px-2 py-1.5 text-[11px]"
              :value="tingkatId ?? ''" @change="gantiTingkat($event.target.value)">
              <option v-for="t in tingkatList" :key="t.id" :value="t.id">{{ t.nama }}</option>
            </select>
          </div>

          <p v-if="galatKecukupan" class="mt-4 text-[12px] text-[#a33333]">{{ galatKecukupan }}</p>
          <ul v-else-if="materiButuhPerhatian.length" class="mt-4 divide-y divide-dashed divide-[#e1e8f2]">
            <li v-for="item in materiButuhPerhatian" :key="item.materiId" class="flex items-center justify-between gap-3 py-3 text-[12px]">
              <div class="min-w-0"><p class="truncate font-bold">{{ item.judul }}</p><p class="mt-1 text-[11px] text-[#71809a]">{{ item.punyaLatihan ? 'Jumlah soal belum mencukupi' : 'Belum memiliki latihan' }}</p></div>
              <button type="button" class="shrink-0 rounded-lg bg-[#f2b82b] px-3 py-1.5 text-[11px] font-bold text-[#18263a]" @click="router.push({ name: 'admin.ujian', query: { tab: 'latihan' } })">Periksa</button>
            </li>
          </ul>
          <div v-else class="py-9 text-center text-[12px] text-[#71809a]">{{ k ? 'Tidak ada kekurangan latihan pada tingkat ini.' : 'Pilih tingkat untuk memeriksa materi.' }}</div>

          <h2 class="mt-7 text-[14px] font-bold">Notifikasi Sistem</h2>
          <div class="mt-3 flex min-h-[120px] flex-col items-center justify-center text-center">
            <strong class="text-[13px]">Belum ada notifikasi baru</strong>
            <p class="mt-1 max-w-[300px] text-[11px] leading-relaxed text-[#71809a]">Notifikasi otomatis belum disediakan oleh endpoint Dashboard Admin.</p>
          </div>
        </article>
      </section>

      <section class="mt-5 rounded-2xl border border-[#e0e7f2] bg-white p-5">
        <h2 class="text-[14px] font-bold">Aktivitas Sistem & Kecukupan Bank Soal</h2>
        <p class="mt-1 text-[11px] text-[#71809a]">Ringkasan kebutuhan bank soal menurut tingkat yang sedang dipilih.</p>
        <div v-if="k" class="mt-4 grid gap-4 md:grid-cols-3">
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Pre-Test per Level</h3>
            <p v-for="x in k.pretestPerLevel" :key="x.level" class="flex justify-between gap-2 py-1.5 text-[11px]"><span>{{ x.label }}</span><span :class="x.kurang ? 'font-bold text-[#d18a00]' : 'text-[#159564]'">{{ x.tersedia }} / {{ x.kuota }}</span></p>
          </div>
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Simulasi per Level</h3>
            <div v-for="s in k.simulasiPerLevel" :key="s.simulasiId" class="mb-2">
              <p class="text-[11px] font-semibold">{{ s.nama }}</p>
              <p v-for="x in s.perLevel" :key="x.level" class="flex justify-between gap-2 py-1 text-[11px] text-[#64748b]"><span>{{ x.label }}</span><span>{{ x.tersedia }} / {{ x.kuota }}</span></p>
            </div>
          </div>
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Putaran Pre-Test</h3>
            <p class="text-[12px] text-[#64748b]">{{ k.putaranPretest.putaran }} putaran · ambang {{ k.putaranPretest.ambang }}</p>
            <button type="button" class="mt-4 rounded-lg border border-[#dfe6f2] px-3 py-2 text-[11px] font-semibold text-[#173e79]" @click="router.push({ name: 'admin.soal', query: { tab: 'soal' } })">Lihat Bank Soal →</button>
          </div>
        </div>
        <p v-else class="py-6 text-center text-[12px] text-[#71809a]">Data kecukupan bank soal belum tersedia.</p>
      </section>
    </template>
  </div>
</template>
