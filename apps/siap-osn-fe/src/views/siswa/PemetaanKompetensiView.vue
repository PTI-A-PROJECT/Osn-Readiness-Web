<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserMenu from '@/components/UserMenu.vue'
import { belajarService } from '@/services/belajar.js'
import { pretestService } from '@/services/pretest.js'
import { riwayatService } from '@/services/riwayat.js'
import { useAuthStore } from '@/stores/auth.js'
import { useMateriStore } from '@/stores/materi.js'
import { usePretestStore, STATUS } from '@/stores/pretest.js'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const pretest = usePretestStore()
const materi = useMateriStore()

const memuat = ref(true)
const riwayat = ref([])
const riwayatMemuat = ref(false)
const riwayatError = ref(false)

const hasil = computed(() => pretest.hasil)
const sedangAnalisis = computed(() => pretest.status === STATUS.MENILAI && !hasil.value)
const namaSiswa = computed(() => auth.nama || 'Siswa')

const namaTingkatById = computed(() =>
  Object.fromEntries(pretest.tingkatList.map((tingkat) => [tingkat.id, tingkat.nama])),
)
const namaTingkat = computed(
  () => namaTingkatById.value[hasil.value?.tingkatId] ?? '',
)
const subjudul = computed(() =>
  namaTingkat.value
    ? `Semangat melanjutkan persiapan Tingkat ${namaTingkat.value}-mu`
    : 'Semangat melanjutkan persiapan OSN-mu',
)

async function muatDaftarRiwayat() {
  await pretest.muatTingkat()
  const { items } = await riwayatService.daftar({
    jenis: 'pretest',
    // Ambil satu ekstra karena percobaan yang sedang dinilai tidak
    // ditampilkan sebagai riwayat sampai nilainya sudah tersedia.
    perPage: 6,
    namaTingkatById: namaTingkatById.value,
  })
  return items
}

let timerPolling = null

function stopPolling() {
  if (timerPolling !== null) {
    clearInterval(timerPolling)
    timerPolling = null
  }
}

async function cekHasilBerkala() {
  if (pretest.status !== STATUS.MENILAI) {
    stopPolling()
    return
  }

  const hasilBaru = await pretest.cekHasil()
  if (!hasilBaru) return

  stopPolling()
  await muatRiwayat()
}

function mulaiPolling() {
  if (timerPolling !== null) return
  timerPolling = setInterval(cekHasilBerkala, 5000)
}

async function muatHasilHalaman() {
  const idDariUrl = Number(route.params.id)
  let daftarRiwayat = null

  try {
    await pretest.muatTingkat()

    if (Number.isFinite(idDariUrl) && idDariUrl > 0) {
      if (pretest.hasil?.id !== idDariUrl) {
        await pretest.lanjutkan({ id: idDariUrl })
      }
    } else if (!pretest.hasil) {
      // Utamakan pengerjaan tersimpan (termasuk yang sedang dinilai).
      // Jika tidak ada, buka percobaan pre-test terbaru dari riwayat.
      const idTersimpan = pretest.pretestId ?? pretest.idTersimpan()?.id ?? null

      if (idTersimpan) {
        await pretest.lanjutkan({ id: idTersimpan })
      } else {
        daftarRiwayat = await muatDaftarRiwayat()
        const idTerakhir = daftarRiwayat.find((item) => item.referensiId)?.referensiId
        if (idTerakhir) await pretest.lanjutkan({ id: idTerakhir })
      }
    }

    if (pretest.status === STATUS.MENGERJAKAN) {
      router.replace({ name: 'siswa.pretest' })
      return
    }
  } catch {
    // Kondisi kosong ditampilkan jika hasil tidak dapat ditemukan/dimuat.
  } finally {
    memuat.value = false
  }

  if (pretest.status === STATUS.MENILAI) {
    await muatRiwayat(daftarRiwayat)
    mulaiPolling()
  } else if (hasil.value) {
    await muatRiwayat(daftarRiwayat)
  }
}

onMounted(muatHasilHalaman)
onBeforeUnmount(stopPolling)

// Nama materi asli dari GET /api/materi, bukan label "Materi {id}".
const namaMateriById = computed(
  () => new Map(materi.daftar.map((item) => [item.id, item.judul])),
)
const prioritasWajib = computed(
  () => new Map((hasil.value?.materiWajib ?? []).map((item) => [item.materiId, item.prioritas])),
)

const tingkatMateriId = computed(() => hasil.value?.tingkatId ?? pretest.tingkatId)

async function muatJudulMateri() {
  const tingkatId = tingkatMateriId.value
  if (!tingkatId || materi.tingkatId === tingkatId) return
  await materi.fetchDaftar({ tingkatId }).catch(() => {})
}

watchEffect(() => {
  if (tingkatMateriId.value) muatJudulMateri()
})

const skor = computed(() =>
  hasil.value?.nilai == null ? 0 : Math.round(hasil.value.nilai),
)
const R = 44
const keliling = 2 * Math.PI * R
const garis = computed(() => (keliling * Math.min(100, Math.max(0, skor.value))) / 100)

const totalSoal = computed(() =>
  (hasil.value?.pemetaan ?? []).reduce((total, item) => total + item.jumlahSoal, 0),
)
const totalBenar = computed(() =>
  (hasil.value?.pemetaan ?? []).reduce((total, item) => total + item.jumlahBenar, 0),
)

const namaMateri = (materiId) =>
  namaMateriById.value.get(materiId) ?? `Materi ${materiId}`

const statusNilai = (nilai) => (nilai >= 70 ? 'ok' : nilai >= 40 ? 'mid' : 'low')
const labelStatus = {
  ok: 'Dikuasai',
  mid: 'Perlu latihan',
  low: 'Belum dikuasai',
}

const ringkasanTopik = computed(() =>
  (hasil.value?.pemetaan ?? []).map((item) => ({
    materiId: item.materiId,
    nama: namaMateri(item.materiId),
    benar: `${item.jumlahBenar}/${item.jumlahSoal}`,
    status: statusNilai(item.persentase ?? 0),
  })),
)

const topik = computed(() =>
  (hasil.value?.pemetaan ?? []).map((item) => ({
    materiId: item.materiId,
    nama: namaMateri(item.materiId),
    wajib: prioritasWajib.value.get(item.materiId) ?? null,
    nilai: item.persentase == null ? 0 : Math.round(item.persentase),
  })),
)

const terkuat = computed(() =>
  topik.value.length
    ? [...topik.value].sort((a, b) => b.nilai - a.nilai)[0]
    : null,
)
const terlemah = computed(() =>
  topik.value.length
    ? [...topik.value].sort((a, b) => a.nilai - b.nilai)[0]
    : null,
)
const jumlahWajib = computed(() => hasil.value?.materiWajib?.length ?? 0)

function formatTanggal(iso, ringkas = false) {
  if (!iso) return '—'
  const tanggal = new Date(iso)
  if (Number.isNaN(tanggal.getTime())) return '—'

  return new Intl.DateTimeFormat('id-ID', ringkas
    ? { day: '2-digit', month: 'short', year: 'numeric' }
    : { day: '2-digit', month: 'long', year: 'numeric' },
  ).format(tanggal)
}

const tanggalSubmit = computed(() => formatTanggal(hasil.value?.disubmitPada))
const teksTren = { naik: '↑ Naik', turun: '↓ Turun', stabil: '→ Stabil' }

const barisSkeleton = computed(() => {
  const lebar = [76, 51, 40, 34, 80, 51]
  const daftar = materi.daftar.slice(0, 6)

  if (daftar.length) {
    return daftar.map((item, index) => ({
      id: item.id,
      nama: item.judul || `Materi ${index + 1}`,
      lebar: `${lebar[index]}%`,
    }))
  }

  return lebar.map((nilai, index) => ({
    id: `placeholder-${index + 1}`,
    nama: `Materi ${index + 1}`,
    lebar: `${nilai}%`,
  }))
})

// Riwayat diambil dari endpoint riwayat pre-test. Detail tiap percobaan
// dipakai untuk menemukan topik terlemah, bukan mengisi tabel dengan data contoh.
async function muatRiwayat(itemsAwal = null) {
  riwayatMemuat.value = true
  riwayatError.value = false

  try {
    const semuaItems = itemsAwal ?? await muatDaftarRiwayat()
    const items = semuaItems
      .filter((item) => !(
        pretest.status === STATUS.MENILAI &&
        item.referensiId === pretest.pretestId
      ))
      .slice(0, 5)
    const tingkatIds = [...new Set(items.map((item) => item.tingkatId).filter(Boolean))]
    const judulMateri = new Map()

    await Promise.all(tingkatIds.map(async (tingkatId) => {
      try {
        const daftarMateri = await belajarService.daftar({ tingkatId })
        daftarMateri.forEach((item) => {
          judulMateri.set(`${tingkatId}:${item.id}`, item.judul)
        })
      } catch {
        // Tabel tetap dapat tampil; nama materi akan memakai fallback ID.
      }
    }))

    const baris = await Promise.all(items.map(async (item) => {
      let detail = item.referensiId === hasil.value?.id ? hasil.value : null

      if (!detail && item.referensiId) {
        try {
          const paket = await pretestService.lihat({ id: item.referensiId })
          if (paket.jenis === 'hasil') detail = paket.hasil
        } catch {
          // Satu detail gagal tidak boleh menghilangkan baris riwayat lainnya.
        }
      }

      const topikTerlemah = [...(detail?.pemetaan ?? [])]
        .filter((topikItem) => topikItem.persentase != null)
        .sort((a, b) => a.persentase - b.persentase)[0]

      return {
        id: item.referensiId ?? item.tanggal,
        tgl: formatTanggal(item.tanggal, true),
        tingkat: namaTingkatById.value[item.tingkatId] ?? '—',
        skorAngka: item.nilai == null ? null : Math.round(item.nilai),
        skor: item.nilai == null ? '—' : `${Math.round(item.nilai)}%`,
        lemah: topikTerlemah
          ? judulMateri.get(`${item.tingkatId}:${topikTerlemah.materiId}`) ??
            (item.tingkatId === hasil.value?.tingkatId
              ? namaMateri(topikTerlemah.materiId)
              : `Materi ${topikTerlemah.materiId}`)
          : '—',
      }
    }))

    // Endpoint mengurutkan riwayat terbaru lebih dahulu. Bandingkan setiap
    // percobaan dengan percobaan yang lebih lama tepat di bawahnya.
    riwayat.value = baris.map((item, index) => {
      const sebelumnya = baris[index + 1]
      let tren = 'stabil'
      if (sebelumnya && item.skorAngka != null && sebelumnya.skorAngka != null) {
        tren = item.skorAngka > sebelumnya.skorAngka
          ? 'naik'
          : item.skorAngka < sebelumnya.skorAngka
            ? 'turun'
            : 'stabil'
      }
      return { ...item, tren }
    })
  } catch {
    riwayatError.value = true
    riwayat.value = []
  } finally {
    riwayatMemuat.value = false
  }
}

function unduhLaporan() {
  const kolom = ['Materi', 'Persentase Penguasaan', 'Status']
  const escapeCsv = (nilai) => `"${String(nilai ?? '').replaceAll('"', '""')}"`
  const baris = topik.value.map((item) => [
    item.nama,
    `${item.nilai}%`,
    labelStatus[statusNilai(item.nilai)],
  ])
  const csv = [kolom, ...baris].map((row) => row.map(escapeCsv).join(',')).join('\r\n')
  const file = new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(file)
  const tautan = document.createElement('a')
  tautan.href = url
  tautan.download = 'laporan-pemetaan-kompetensi.csv'
  document.body.appendChild(tautan)
  tautan.click()
  tautan.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] text-[#0f1b33]">
    <header class="flex min-h-[60px] items-center justify-between gap-4 border-b border-[#e6ebf2] bg-white px-5 py-3 sm:px-7">
      <div class="min-w-0">
        <h1 class="text-[15px] font-bold leading-tight sm:text-[17px]">
          Halo {{ namaSiswa }}! 👋
        </h1>
        <p class="mt-1 truncate text-[11px] text-[#718096] sm:text-xs">{{ subjudul }}</p>
      </div>
      <UserMenu />
    </header>

    <main class="px-4 py-5 sm:px-5 lg:px-6">
      <div class="pk">
        <!-- STATE: SEDANG DIANALISIS -->
        <template v-if="memuat || sedangAnalisis">
          <section class="section">
            <p class="eyebrow">01 · HASIL PRE-TEST</p>
            <h2>Hasil Pre-Test Terakhir{{ namaTingkat ? ` ${namaTingkat}` : '' }}</h2>
            <p class="desc">Ringkasan skor dan ketepatan jawabanmu pada pre-test paling baru.</p>

            <div class="grid-2">
              <div class="card pad">
                <h3>Skor Keseluruhan</h3>
                <div class="skor-wrap">
                  <div class="donut skeleton-donut" aria-label="Skor sedang dihitung">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                      <circle cx="50" cy="50" :r="R" class="d-track" />
                      <circle
                        cx="50" cy="50" :r="R" class="d-fill"
                        stroke-dasharray="58 276"
                        transform="rotate(-125 50 50)"
                      />
                    </svg>
                    <div class="donut-text">
                      <strong class="skeleton skeleton-score"></strong>
                      <span>Skor</span>
                    </div>
                  </div>
                  <div class="score-detail skeleton-detail">
                    <span class="skeleton skeleton-line line-medium"></span>
                    <span class="skeleton skeleton-line line-long"></span>
                    <div class="chips">
                      <span class="skeleton skeleton-chip"></span>
                      <span class="skeleton skeleton-chip wide"></span>
                    </div>
                    <p class="loading-note"><i class="loading-dot"></i>Sedang menghitung skor...</p>
                  </div>
                </div>
              </div>

              <div class="card pad">
                <div class="loading-card-head">
                  <h3>Ringkasan Per Topik</h3>
                  <span class="loading-caption"><i class="loading-dot"></i>Menganalisis hasil...</span>
                </div>
                <ul class="ringkas skeleton-list" aria-label="Ringkasan topik sedang dianalisis">
                  <li v-for="baris in barisSkeleton.slice(0, 5)" :key="baris.id">
                    <span class="skeleton skeleton-line" :style="{ width: baris.lebar }"></span>
                    <span class="skeleton skeleton-line skeleton-value"></span>
                  </li>
                </ul>
                <p class="skeleton-footnote">Sedang menganalisis hasil per topik...</p>
              </div>
            </div>
          </section>

          <section class="section">
            <p class="eyebrow">02 · GRAFIK PER TOPIK</p>
            <h2>Peta Kompetensi Saat Ini</h2>
            <p class="desc">Persentase penguasaan tiap topik berdasarkan hasil pemetaan kompetensimu.</p>

            <div class="card pad">
              <div class="bar-head loading-bar-head">
                <div class="loading-title-wrap">
                  <div class="loading-title-line">
                    <h3>Semua Topik</h3>
                    <span class="analysis-pill"><i class="loading-ring"></i>Menganalisis hasil kompetensi...</span>
                  </div>
                  <p>Proses ini biasanya hanya membutuhkan beberapa detik.</p>
                </div>
                <button type="button" class="link-btn" disabled>Unduh laporan</button>
              </div>

              <div class="legend">
                <span><i class="dot ok"></i>Dikuasai (≥70%)</span>
                <span><i class="dot mid"></i>Perlu latihan (40–69%)</span>
                <span><i class="dot low"></i>Belum dikuasai (&lt;40%)</span>
              </div>

              <div v-for="baris in barisSkeleton" :key="baris.id" class="bar-row skeleton-row">
                <span class="bar-name">{{ baris.nama }}</span>
                <div class="track skeleton-track">
                  <div class="skeleton-fill" :style="{ width: baris.lebar }"></div>
                </div>
                <span class="skeleton skeleton-small-block"></span>
                <span class="skeleton skeleton-status-block"></span>
              </div>
            </div>
          </section>
        </template>

        <!-- STATE: BELUM ADA HASIL -->
        <div v-else-if="!hasil" class="card pad kosong">
          <h3>Belum ada hasil pre-test</h3>
          <p class="desc">Ikuti pre-test terlebih dahulu untuk melihat pemetaan kompetensimu.</p>
          <button type="button" class="btn-amber" @click="router.push({ name: 'siswa.pretest' })">
            Ikuti Pre-Test →
          </button>
        </div>

        <!-- STATE: HASIL SUDAH SELESAI -->
        <template v-else>
          <section class="section">
            <p class="eyebrow">01 · HASIL PRE-TEST</p>
            <h2>Hasil Pre-Test Terakhir{{ namaTingkat ? ` ${namaTingkat}` : '' }}</h2>
            <p class="desc">Ringkasan skor dan ketepatan jawabanmu pada pre-test paling baru.</p>

            <div class="grid-2">
              <div class="card pad">
                <h3>Skor Keseluruhan</h3>
                <div class="skor-wrap">
                  <div class="donut">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                      <circle cx="50" cy="50" :r="R" class="d-track" />
                      <circle
                        cx="50" cy="50" :r="R" class="d-fill"
                        :stroke-dasharray="`${garis} ${keliling}`"
                        transform="rotate(-90 50 50)"
                      />
                    </svg>
                    <div class="donut-text">
                      <strong>{{ skor }}%</strong>
                      <span>Skor</span>
                    </div>
                  </div>
                  <div class="score-detail">
                    <p class="pt-title">Pre-Test Tingkat {{ namaTingkat || '—' }}</p>
                    <p class="pt-meta">Dikerjakan {{ tanggalSubmit }} · {{ totalSoal }} soal</p>
                    <div class="chips">
                      <span v-if="namaTingkat" class="chip blue">Tingkat {{ namaTingkat }}</span>
                      <span class="chip amber">{{ totalBenar }} dari {{ totalSoal }} benar</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card pad">
                <h3>Ringkasan Per Topik</h3>
                <ul class="ringkas">
                  <li v-for="item in ringkasanTopik" :key="item.materiId">
                    <span>{{ item.nama }}</span>
                    <span><b :class="item.status">{{ item.benar }}</b> benar</span>
                  </li>
                  <li v-if="!ringkasanTopik.length" class="empty-inline">Belum ada rincian topik.</li>
                </ul>
              </div>
            </div>
          </section>

          <section class="section">
            <p class="eyebrow">02 · GRAFIK PER TOPIK</p>
            <h2>Peta Kompetensi Saat Ini</h2>
            <p class="desc">Persentase penguasaan tiap topik berdasarkan hasil pre-test terakhir.</p>

            <div class="card pad">
              <div class="bar-head">
                <h3>Semua Topik</h3>
                <div class="legend">
                  <span><i class="dot ok"></i>Dikuasai (≥70%)</span>
                  <span><i class="dot mid"></i>Perlu latihan (40–69%)</span>
                  <span><i class="dot low"></i>Belum dikuasai (&lt;40%)</span>
                </div>
                <button
                  type="button"
                  class="link-btn"
                  :disabled="!topik.length"
                  @click="unduhLaporan"
                >
                  Unduh laporan
                </button>
              </div>

              <div v-for="item in topik" :key="item.materiId" class="bar-row">
                <span class="bar-name">{{ item.nama }}</span>
                <div
                  class="track"
                  role="progressbar"
                  :aria-label="`Penguasaan ${item.nama}`"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  :aria-valuenow="item.nilai"
                >
                  <div
                    class="fill"
                    :class="statusNilai(item.nilai)"
                    :style="{ width: `${item.nilai}%` }"
                  ></div>
                </div>
                <span class="bar-val">{{ item.nilai }}%</span>
                <span class="bar-label" :class="statusNilai(item.nilai)">
                  {{ labelStatus[statusNilai(item.nilai)] }}
                </span>
              </div>

              <p v-if="!topik.length" class="empty-inline">Belum ada data penguasaan per topik.</p>
            </div>

            <div class="card pad analisis">
              <h3 class="big">Analisis Hasil Kompetensi</h3>
              <p class="desc tight">Ringkasan berdasarkan hasil pemetaan kompetensimu saat ini.</p>

              <div class="point">
                <i class="dot ok"></i>
                <div>
                  <p class="point-tag ok">KEKUATAN UTAMA</p>
                  <p v-if="terkuat">
                    {{ terkuat.nama }} menjadi topik terkuatmu dengan penguasaan {{ terkuat.nilai }}%.
                  </p>
                  <p v-else>Belum ada data topik.</p>
                </div>
              </div>

              <div class="point">
                <i class="dot low"></i>
                <div>
                  <p class="point-tag low">PERLU DIPRIORITASKAN</p>
                  <p v-if="terlemah">
                    {{ terlemah.nama }} memiliki penguasaan terendah sebesar {{ terlemah.nilai }}%.
                  </p>
                  <p v-else>Belum ada data topik.</p>
                </div>
              </div>

              <div class="point">
                <i class="dot info"></i>
                <div>
                  <p class="point-tag info">SARAN BELAJAR</p>
                  <p v-if="terlemah">
                    Fokuskan latihan berikutnya pada {{ terlemah.nama }}{{ jumlahWajib ? ` sebelum melanjutkan ke topik lain. ${jumlahWajib} materi wajib juga perlu kamu selesaikan.` : ' sebelum melanjutkan ke topik lain.' }}
                  </p>
                  <p v-else>Ikuti materi yang direkomendasikan untuk meningkatkan penguasaanmu.</p>
                </div>
              </div>

              <div class="cta">
                <div>
                  <p class="cta-title">Lanjutkan belajarmu</p>
                  <p class="cta-desc">Lihat materi yang direkomendasikan berdasarkan topik yang perlu kamu tingkatkan.</p>
                </div>
                <button type="button" class="btn-amber" @click="router.push({ name: 'siswa.materi' })">
                  Lihat Rekomendasi Materi →
                </button>
              </div>
            </div>
          </section>
        </template>

        <!-- RIWAYAT TETAP TERLIHAT SAAT HASIL TERBARU DIANALISIS -->
        <section v-if="memuat || sedangAnalisis || hasil" class="section">
          <p class="eyebrow">03 · RIWAYAT PEMETAAN</p>
          <h2>Perkembangan Pemetaan dari Waktu ke Waktu</h2>
          <p class="desc">Bandingkan skor pemetaan kompetensimu di setiap percobaan pre-test sebelumnya.</p>

          <div class="card pad">
            <h3>Riwayat Pemetaan Kompetensi</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Tingkat</th>
                    <th>Skor Keseluruhan</th>
                    <th>Topik Terlemah</th>
                    <th>Tren</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="riwayatMemuat || (memuat && !riwayat.length)">
                    <td colspan="5" class="riwayat-kosong">Memuat riwayat pemetaan...</td>
                  </tr>
                  <tr v-else-if="riwayatError">
                    <td colspan="5" class="riwayat-kosong">
                      Riwayat belum dapat dimuat.
                      <button type="button" class="retry-btn" @click="muatRiwayat()">Coba lagi</button>
                    </td>
                  </tr>
                  <tr v-else-if="!riwayat.length">
                    <td colspan="5" class="riwayat-kosong">Belum ada riwayat pre-test sebelumnya.</td>
                  </tr>
                  <tr v-for="item in riwayat" v-else :key="item.id">
                    <td>{{ item.tgl }}</td>
                    <td>{{ item.tingkat }}</td>
                    <td><b>{{ item.skor }}</b></td>
                    <td>{{ item.lemah }}</td>
                    <td><span class="badge" :class="item.tren">{{ teksTren[item.tren] }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
.pk {
  --ok: #10b981;
  --mid: #3b82f6;
  --low: #ef4444;
  --amber: #f59e0b;
  --gold-dark: #c98200;
  --muted: #718096;
  --line: #e6ebf2;
  font-size: 13px;
}

.section { padding: 18px 0 12px; }
.section:first-child { padding-top: 0; }
.section h2 { margin: 0; font-size: 16px; font-weight: 650; line-height: 1.35; }
.eyebrow { margin: 0 0 4px; color: var(--gold-dark); font-size: 10px; font-weight: 700; letter-spacing: .04em; }
.desc { margin: 3px 0 12px; color: var(--muted); font-size: 11px; line-height: 1.5; }
.desc.tight { margin-bottom: 7px; }

.card { min-width: 0; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 1px 3px rgb(15 30 60 / 4%); }
.pad { padding: 17px 18px; }
.card h3 { margin: 0 0 12px; font-size: 11px; font-weight: 650; }
.card h3.big { margin-bottom: 3px; font-size: 14px; }
.kosong { padding: 38px 22px; text-align: center; }
.kosong h3 { font-size: 15px; }
.kosong .desc { margin-bottom: 16px; }
.empty-inline { padding: 8px 0; color: var(--muted); font-size: 11px; }
.riwayat-kosong { padding: 18px 8px; color: var(--muted); text-align: center; white-space: normal; }
.retry-btn { margin-left: 6px; color: #1d4ed8; font-weight: 600; text-decoration: underline; }
.analisis { margin-top: 12px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Skor keseluruhan */
.skor-wrap { display: flex; align-items: center; gap: 16px; min-height: 94px; }
.donut { position: relative; width: 82px; height: 82px; flex: none; }
.donut svg { width: 100%; height: 100%; }
.d-track { fill: none; stroke: #eef2f7; stroke-width: 10; }
.d-fill { fill: none; stroke: var(--amber); stroke-width: 10; stroke-linecap: round; }
.donut-text { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.donut-text strong { font-size: 19px; line-height: 1.1; }
.donut-text span { color: var(--muted); font-size: 9px; }
.score-detail { min-width: 0; }
.pt-title { margin: 0; font-size: 12px; font-weight: 650; }
.pt-meta { margin: 4px 0 9px; color: var(--muted); font-size: 10px; }
.chips { display: flex; flex-wrap: wrap; gap: 5px; }
.chip { padding: 3px 8px; border: 1px solid transparent; border-radius: 999px; font-size: 9px; font-weight: 600; }
.chip.blue { border-color: #d8e5ff; background: #eff5ff; color: #1d4ed8; }
.chip.amber { border-color: #f8df9e; background: #fff8e7; color: #a16207; }

/* Ringkasan per topik */
.ringkas { margin: 0; padding: 0; list-style: none; }
.ringkas li { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; color: #344258; font-size: 10px; }
.ringkas b { font-weight: 650; }
.ringkas b.ok { color: var(--ok); }
.ringkas b.mid { color: var(--mid); }
.ringkas b.low { color: var(--low); }

/* Grafik penguasaan */
.bar-head { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #edf0f5; }
.bar-head h3 { flex: none; margin: 0; }
.legend { display: flex; flex: 1; flex-wrap: wrap; gap: 9px 12px; color: var(--muted); font-size: 9px; }
.legend span { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
.dot { display: inline-block; width: 7px; height: 7px; flex: none; border-radius: 50%; }
.dot.ok { background: var(--ok); }
.dot.mid, .dot.info { background: var(--mid); }
.dot.low { background: var(--amber); }
.link-btn { flex: none; border: 0; background: none; color: #25334a; font-size: 10px; font-weight: 600; cursor: pointer; }
.link-btn:disabled { cursor: not-allowed; opacity: .45; }
.bar-row { display: grid; grid-template-columns: 138px minmax(80px, 1fr) 38px 94px; align-items: center; gap: 12px; padding: 6px 0; font-size: 10px; }
.bar-name { overflow: hidden; color: #344258; text-overflow: ellipsis; white-space: nowrap; }
.track { height: 7px; overflow: hidden; border-radius: 999px; background: #eef2f7; }
.fill { height: 100%; border-radius: 999px; transition: width .25s ease; }
.fill.ok { background: var(--ok); }
.fill.mid { background: var(--mid); }
.fill.low { background: linear-gradient(90deg, #f43f5e, var(--amber)); }
.bar-val { color: #475569; text-align: right; }
.bar-label { color: #475569; font-size: 9px; text-align: right; }
.bar-label.ok { color: #059669; }
.bar-label.mid { color: #2563eb; }
.bar-label.low { color: #dc2626; }

/* Analisis dan rekomendasi */
.point { display: flex; gap: 10px; padding: 10px 0 5px; border-bottom: 1px solid #edf0f5; }
.point:last-of-type { border-bottom: 0; }
.point .dot { margin-top: 5px; }
.point p { margin: 0; color: #40516b; font-size: 10px; line-height: 1.55; }
.point-tag { margin-bottom: 2px !important; font-size: 9px !important; font-weight: 700; letter-spacing: .03em; }
.point-tag.ok { color: #059669 !important; }
.point-tag.low { color: #e11d48 !important; }
.point-tag.info { color: #2563eb !important; }
.point .dot.low { background: #f43f5e; }
.cta { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 14px; padding-top: 13px; border-top: 1px solid var(--line); }
.cta-title { margin: 0; color: #1e293b; font-size: 11px; font-weight: 650; }
.cta-desc { margin: 3px 0 0; color: var(--muted); font-size: 10px; }
.btn-amber { border: 0; border-radius: 7px; padding: 9px 14px; background: var(--amber); color: #fff; font-size: 10px; font-weight: 650; cursor: pointer; white-space: nowrap; }
.btn-amber:hover { filter: brightness(.96); }

/* Riwayat pemetaan */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { padding: 10px 12px; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 9px; font-weight: 500; text-align: left; white-space: nowrap; }
td { padding: 10px 12px; border-bottom: 1px solid #f0f2f6; color: #40516b; font-size: 10px; white-space: nowrap; }
tbody tr:last-child td { border-bottom: 0; }
td b { color: #1e293b; font-weight: 700; }
.badge { display: inline-block; padding: 3px 7px; border-radius: 5px; font-size: 9px; font-weight: 600; }
.badge.naik { background: #e5f8ef; color: #059669; }
.badge.turun { background: #fff0f1; color: #e11d48; }
.badge.stabil { background: #f1f5f9; color: #64748b; }


/* Skeleton state saat hasil pre-test sedang dianalisis */
.skeleton {
  display: inline-block;
  border-radius: 5px;
  background: linear-gradient(90deg, #e7ecf3 25%, #f3f6fa 45%, #e7ecf3 65%);
  background-size: 220% 100%;
  animation: skeleton-shimmer 1.35s ease-in-out infinite;
}
@keyframes skeleton-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}
.skeleton-donut .d-fill { stroke: #fbb32f; }
.skeleton-score { width: 23px; height: 12px; }
.skeleton-detail { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
.skeleton-line { height: 12px; }
.line-medium { width: 118px; }
.line-long { width: 164px; }
.skeleton-chip { width: 76px; height: 19px; border-radius: 999px; }
.skeleton-chip.wide { width: 92px; }
.loading-note, .loading-caption {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 2px 0 0;
  color: #8493a8;
  font-size: 9px;
  white-space: nowrap;
}
.loading-dot {
  display: inline-block;
  width: 7px;
  height: 7px;
  flex: none;
  border-radius: 50%;
  background: #f59e0b;
  animation: loading-pulse 1.2s ease-in-out infinite;
}
@keyframes loading-pulse {
  50% { opacity: .35; transform: scale(.8); }
}
.loading-card-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.loading-card-head h3 { margin-bottom: 12px; }
.skeleton-list { margin-top: 0; }
.skeleton-list li { min-height: 28px; align-items: center; border-bottom: 1px solid #edf1f6; }
.skeleton-list li:last-child { border-bottom: 0; }
.skeleton-list .skeleton-line { height: 13px; }
.skeleton-list .skeleton-value { width: 52px; }
.skeleton-footnote { margin: 4px 0 0; color: #a1aec0; font-size: 8px; text-align: right; }
.loading-bar-head {
  align-items: flex-start;
  justify-content: space-between;
  padding-bottom: 13px;
  border-bottom: 1px solid #edf0f5;
}
.loading-title-wrap { min-width: 0; }
.loading-title-line { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; }
.loading-title-line h3 { margin: 0; }
.loading-title-wrap > p { margin: 5px 0 0; color: #9aa8bc; font-size: 9px; }
.analysis-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 9px;
  border: 1px solid #fde5a7;
  border-radius: 999px;
  background: #fffbeb;
  color: #b66b00;
  font-size: 9px;
  white-space: nowrap;
}
.loading-ring {
  width: 10px;
  height: 10px;
  border: 1.5px solid #f3b33d;
  border-right-color: transparent;
  border-radius: 50%;
  animation: loading-rotate .9s linear infinite;
}
@keyframes loading-rotate { to { transform: rotate(360deg); } }
.loading-bar-head + .legend { margin: 2px 0 12px; }
.skeleton-row { min-height: 33px; }
.skeleton-track { background: #f0f3f8; }
.skeleton-fill { height: 100%; border-radius: 999px; background: #e1e7ef; }
.skeleton-small-block { width: 26px; height: 14px; justify-self: end; }
.skeleton-status-block { width: 65px; height: 16px; justify-self: end; }

@media (max-width: 900px) {
  .grid-2 { grid-template-columns: 1fr; }
  .bar-head { align-items: flex-start; flex-direction: column; gap: 9px; }
  .bar-row { grid-template-columns: 110px minmax(70px, 1fr) 36px; gap: 8px; }
  .bar-label { display: none; }
}
@media (max-width: 560px) {
  .skor-wrap { align-items: flex-start; gap: 12px; }
  .donut { width: 72px; height: 72px; }
  .cta { align-items: stretch; flex-direction: column; }
  .btn-amber { width: 100%; }
  th, td { padding-right: 9px; padding-left: 9px; }
}
</style>
