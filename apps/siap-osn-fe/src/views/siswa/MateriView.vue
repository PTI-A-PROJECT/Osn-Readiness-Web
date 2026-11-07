<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'
import { useMateriStore } from '@/stores/materi.js'
import { usePretestStore, STATUS } from '@/stores/pretest.js'
import { useProgressStore } from '@/stores/progress.js'
import { belajarService } from '@/services/belajar.js'
import { riwayatService } from '@/services/riwayat.js'
import { pesanError } from '@/lib/errors.js'
import { renderKonten } from '@/lib/richtext.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const materi = useMateriStore()
const pretest = usePretestStore()
const progress = useProgressStore()

const katalog = ref([])
const memuat = ref(true)
const memuatDetail = ref(false)
const errorKatalog = ref(false)
const pencarian = ref('')
const filterTingkat = ref('semua')
const filterJenis = ref('semua')
const filterStatus = ref('semua')
function resetFilter() {
  pencarian.value = ''
  filterTingkat.value = 'semua'
  filterJenis.value = 'semua'
  filterStatus.value = 'semua'
}
const dibukaId = ref(null)
const submateriMenandai = ref(null)
const submateriAktifId = ref(null)
const pembacaRef = ref(null)
const videoAktifIndex = ref(0)
const videoAktif = computed(() => detail.value?.videos?.[videoAktifIndex.value] ?? null)
const videoEmbedUrl = (video) => video?.tipe === 'playlist'
  ? 'https://www.youtube-nocookie.com/embed/videoseries?list=' + encodeURIComponent(video.youtubeId)
  : 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(video?.youtubeId ?? '')

const detail = computed(() => materi.detail)
const submateri = computed(() => detail.value?.submateri ?? [])
const jumlahSubmateriSelesai = computed(() => submateri.value.filter((bagian) => bagian.selesai).length)
const semuaSubmateriSelesai = computed(() => submateri.value.length > 0 && submateri.value.every((bagian) => bagian.selesai))
const persentaseDetail = computed(() => submateri.value.length
  ? Math.round((jumlahSubmateriSelesai.value / submateri.value.length) * 100)
  : 0)
const hasilPretest = computed(() => pretest.hasil)
const sudahPretest = computed(() => Boolean(hasilPretest.value?.id))

function namaTingkat(id) {
  return pretest.tingkatList.find((t) => t.id === id)?.nama || 'Tingkat'
}

function statusMateri(m) {
  if (m.progress?.status === 'selesai') return 'selesai'
  if (m.progress?.status === 'belajar') return 'belajar'
  return 'belum'
}

function statusLabel(m) {
  const status = statusMateri(m)
  if (status === 'selesai') return 'Selesai'
  if (status === 'belajar') return 'Sedang berjalan'
  return 'Belum dimulai'
}

function persentase(m) {
  if (statusMateri(m) === 'selesai') return 100
  return Math.max(0, Math.min(100, Number(m.progress?.persentase ?? 0)))
}

function jenisMateri(m) {
  // API belajar saat ini menyediakan modul teks/file; belum ada kontrak video.
  return m.jenisMateri === 'video' || m.videoUrl ? 'Video' : 'Modul'
}

const tingkatOptions = computed(() => [
  { label: 'Semua', value: 'semua' },
  ...pretest.tingkatList.map((t) => ({ label: t.nama, value: String(t.id) })),
])

const materiTersaring = computed(() => {
  const q = pencarian.value.trim().toLocaleLowerCase('id')
  return katalog.value.filter((m) => {
    const cocokNama = !q || m.judul.toLocaleLowerCase('id').includes(q) ||
      (m.deskripsi || '').toLocaleLowerCase('id').includes(q)
    const cocokTingkat = filterTingkat.value === 'semua' || String(m.tingkatId) === filterTingkat.value
    const cocokJenis = filterJenis.value === 'semua' || jenisMateri(m).toLowerCase() === filterJenis.value
    const cocokStatus = filterStatus.value === 'semua' ||
      (filterStatus.value === 'selesai' && statusMateri(m) === 'selesai') ||
      (filterStatus.value === 'berjalan' && statusMateri(m) === 'belajar') ||
      (filterStatus.value === 'belum' && statusMateri(m) === 'belum')
    return cocokNama && cocokTingkat && cocokJenis && cocokStatus
  })
})

const paketLatihan = computed(() => katalog.value.filter((m) => m.quizId || m.latihanTersedia))
const daftarRekomendasi = computed(() => {
  const wajib = hasilPretest.value?.materiWajib ?? []
  const peta = new Map((hasilPretest.value?.pemetaan ?? []).map((item) => [item.materiId, item]))
  const materiById = new Map(katalog.value.map((item) => [item.id, item]))

  return wajib
    .map((item) => {
      const m = materiById.get(item.materiId)
      const hasil = peta.get(item.materiId)
      if (!m) return null
      return {
        ...m,
        prioritas: item.prioritas,
        nilaiPemetaan: hasil?.persentase ?? null,
      }
    })
    .filter(Boolean)
    .sort((a, b) => a.prioritas - b.prioritas)
})

const rekomendasiStatus = computed(() => {
  if (pretest.status === STATUS.MENILAI) return 'Pre-test sedang dianalisis'
  return sudahPretest.value ? 'Sudah Pre-Test · Rekomendasi tersedia' : 'Belum Pre-Test'
})

async function muatHasilPretest() {
  if (pretest.hasil || pretest.status === STATUS.MENILAI || pretest.status === STATUS.MENGERJAKAN) return

  try {
    const { items } = await riwayatService.daftar({
      jenis: 'pretest',
      perPage: 1,
      namaTingkatById: Object.fromEntries(pretest.tingkatList.map((t) => [t.id, t.nama])),
    })
    const idTerakhir = items[0]?.referensiId
    if (idTerakhir) await pretest.lanjutkan({ id: idTerakhir })
  } catch {
    // Empty state rekomendasi tetap ditampilkan jika belum ada hasil.
  }
}

async function muatKatalog() {
  memuat.value = true
  errorKatalog.value = false

  try {
    await pretest.muatTingkat()
    await progress.fetchDashboard().catch(() => {})

    const tingkatTerbuka = pretest.tingkatList.filter((t) => t.terbuka)
    const tingkatAktifId = progress.data.tingkatAktifId
    const hasilDaftar = await Promise.allSettled(
      tingkatTerbuka.map(async (tingkat) => ({
        tingkat,
        daftar: await belajarService.daftar({ tingkatId: tingkat.id }),
      })),
    )

    const berhasil = hasilDaftar
      .filter((hasil) => hasil.status === 'fulfilled')
      .flatMap((hasil) => hasil.value.daftar.map((m) => ({
        ...m,
        tingkatNama: hasil.value.tingkat.nama,
        tingkatTerbuka: true,
      })))

    katalog.value = berhasil.sort((a, b) => {
      if (a.tingkatId === tingkatAktifId && b.tingkatId !== tingkatAktifId) return -1
      if (b.tingkatId === tingkatAktifId && a.tingkatId !== tingkatAktifId) return 1
      if (a.tingkatId !== b.tingkatId) return a.tingkatId - b.tingkatId
      if (a.wajib !== b.wajib) return a.wajib ? -1 : 1
      return (a.prioritas ?? a.urutan) - (b.prioritas ?? b.urutan)
    })

    if (!katalog.value.length && hasilDaftar.every((hasil) => hasil.status === 'rejected')) {
      errorKatalog.value = true
    }

    await muatHasilPretest()
  } catch (err) {
    errorKatalog.value = true
    toast.add({ severity: 'error', summary: 'Gagal memuat materi', detail: pesanError(err), life: 4000 })
  } finally {
    memuat.value = false
  }
}

async function buka(id) {
  dibukaId.value = id
  memuatDetail.value = true
  try {
    await materi.fetchDetail({ id })
    videoAktifIndex.value = 0
    submateriAktifId.value = materi.detail?.submateri?.[0]?.id ?? null
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal membuka materi', detail: pesanError(err), life: 4000 })
    dibukaId.value = null
  } finally {
    memuatDetail.value = false
    await nextTick()
    if (dibukaId.value === id && pembacaRef.value) {
      pembacaRef.value.scrollTop = 0
      perbaruiSubmateriAktif()
    }
  }
}

function tutupDetail() {
  dibukaId.value = null
  submateriAktifId.value = null
}

function lompatKeSubmateri(id) {
  const container = pembacaRef.value
  if (!container) return
  const targetId = id === 'latihan' ? 'latihan-materi' : 'submateri-' + id
  const target = Array.from(container.querySelectorAll('.lesson-section, .lesson-final-practice')).find((el) => el.id === targetId)
  if (!target) return
  submateriAktifId.value = id
  const top = target.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop
  container.scrollTo({ top, behavior: 'smooth' })
}

function perbaruiSubmateriAktif() {
  const container = pembacaRef.value
  if (!container) return
  const nodes = Array.from(container.querySelectorAll('.lesson-section, .lesson-final-practice'))
  const batas = container.getBoundingClientRect().top + 80
  let aktif = submateri.value[0]?.id ?? null
  for (const node of nodes) {
    if (node.getBoundingClientRect().top > batas) break
    aktif = node.id === 'latihan-materi' ? 'latihan' : submateri.value.find((x) => 'submateri-' + x.id === node.id)?.id ?? aktif
  }
  if (nodes.length && container.scrollTop + container.clientHeight >= container.scrollHeight - 4) aktif = 'latihan'
  submateriAktifId.value = aktif
}

async function tandaiSubmateriSelesai(bagian) {
  const id = dibukaId.value
  if (!id || !bagian || bagian.selesai || submateriMenandai.value) return

  submateriMenandai.value = bagian.id
  try {
    const tersimpan = await belajarService.tandaiSubmateriSelesai({
      id,
      submateriId: bagian.id,
    })
    const progressTersimpan = {
      status: tersimpan.status,
      persentase: tersimpan.persentase,
      tanggalSelesai: tersimpan.tanggalSelesai,
    }
    const item = katalog.value.find((m) => m.id === id)
    if (item) item.progress = progressTersimpan

    if (detail.value?.id === id) {
      detail.value.progress = progressTersimpan
      detail.value.submateri = tersimpan.submateri
    }

    await progress.fetchDashboard({ force: true }).catch(() => {})
    toast.add({
      severity: 'success',
      summary: 'Submateri selesai',
      detail: 'Progress materi ' + tersimpan.persentase + '% tersimpan.',
      life: 3500,
    })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal menyimpan', detail: pesanError(err), life: 4000 })
  } finally {
    submateriMenandai.value = null
  }
}

function mulaiLatihan(m) {
  if (!m.quizId) return
  router.push({ name: 'siswa.latihan', params: { quizId: m.quizId } })
}

function labelNilai(m) {
  return m.nilaiTerbaik == null ? 'Belum dikerjakan' : `Nilai terbaik ${Math.round(m.nilaiTerbaik)}`
}

function bukaPretest() {
  router.push({ name: 'siswa.pretest' })
}

onMounted(muatKatalog)
</script>

<template>
  <div class="materi-page" :class="{ 'is-detail-open': Boolean(dibukaId) }">
    <header class="page-header">
      <div class="header-copy">
        <h1>{{ route.meta.title || 'Materi' }}</h1>
        <p>Materi, latihan soal, dan rekomendasi yang disesuaikan dengan kompetensimu</p>
      </div>
      <UserMenu />
    </header>

    <main class="materi-main">
      <!-- DETAIL MATERI -->
      <section v-if="dibukaId" class="detail-view">
        <button type="button" class="back-link detail-breadcrumb" @click="tutupDetail">
          ← Materi <span aria-hidden="true">/</span> {{ detail?.judul || 'Detail Materi' }}
        </button>

        <div class="detail-panel">
          <div v-if="memuatDetail" class="detail-loading">Memuat materi...</div>
          <template v-else-if="detail">
            <div class="detail-overview">
              <div class="detail-overview-copy">
                <div class="detail-badges">
                  <span class="pill pill-blue">{{ detail.wajib ? 'Materi Wajib' : 'Materi OSN' }}</span>
                  <span class="pill" :class="statusMateri(detail) === 'selesai' ? 'pill-green' : 'pill-amber'">
                    {{ statusLabel(detail) }}
                  </span>
                </div>
                <h2>{{ detail.judul }}</h2>
                <p>{{ detail.deskripsi || 'Pelajari materi ini untuk memperkuat kompetensimu.' }}</p>
              </div>

              <div class="detail-progress">
                <div class="detail-progress-label">
                  <span>Progress Membaca</span>
                  <strong>{{ persentaseDetail }}% selesai</strong>
                </div>
                <div class="learning-progress-track">
                  <div class="progress-fill" :style="{ width: persentaseDetail + '%' }"></div>
                </div>
                <span class="detail-progress-meta">
                  {{ jumlahSubmateriSelesai }} dari {{ submateri.length }} submateri selesai
                </span>
              </div>
            </div>

            <img
              v-if="detail.gambarUrl"
              :src="detail.gambarUrl"
              alt="Ilustrasi materi"
              class="detail-image"
            />

            <div v-if="submateri.length" class="lesson-layout">
              <div ref="pembacaRef" class="lesson-reader" tabindex="0" aria-label="Konten pembelajaran" @scroll.passive="perbaruiSubmateriAktif">
                <section v-if="detail.videos?.length" class="lesson-section lesson-video-playlist" aria-label="Video pembelajaran">
                  <header class="lesson-section-header"><div class="lesson-section-title"><div class="lesson-section-heading-copy"><h3>Video Pembelajaran</h3><p class="mt-1 text-sm text-slate-500">{{ detail.videos.length }} video dalam playlist materi ini</p></div></div></header>
                  <div class="lesson-video-frame mt-4 overflow-hidden rounded-xl bg-slate-950">
                    <iframe v-if="videoAktif" :src="videoEmbedUrl(videoAktif)" :title="videoAktif.judul" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                  </div>
                  <div class="mt-3 grid gap-2">
                    <button v-for="(video, index) in detail.videos" :key="video.id" type="button" class="flex w-full items-center gap-3 rounded-xl border px-3 py-3 text-left transition" :class="videoAktifIndex === index ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white hover:bg-slate-50'" @click="videoAktifIndex = index">
                      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">{{ index + 1 }}</span>
                      <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ video.judul }}</span><span class="text-xs text-slate-500">{{ video.tipe === 'playlist' ? 'Playlist YouTube' : 'Video YouTube' }}</span></span>
                      <span v-if="videoAktifIndex === index" class="text-xs font-semibold text-blue-700">Diputar</span>
                    </button>
                  </div>
                </section>
                <article
                  v-for="(bagian, index) in submateri"
                  :id="'submateri-' + bagian.id"
                  :key="bagian.id"
                  class="lesson-section"
                >
                  <header class="lesson-section-header">
                    <div class="lesson-section-title">
                      <span class="lesson-section-number">{{ index + 1 }}.</span>
                      <div class="lesson-section-heading-copy">
                        <h3>{{ bagian.judul }}</h3>
                      </div>
                    </div>
                    <span class="lesson-status" :class="{ 'is-complete': bagian.selesai }">
                      <span aria-hidden="true">{{ bagian.selesai ? '✓' : '○' }}</span>
                      {{ bagian.selesai ? 'Selesai' : 'Belum selesai' }}
                    </span>
                  </header>

                  <div
                    v-if="bagian.konten"
                    class="konten-richtext lesson-content-body"
                    v-html="renderKonten(bagian.konten)"
                  ></div>
                  <p v-else class="submateri-empty">Belum ada isi pada bagian ini.</p>

                  <div class="lesson-completion" :class="{ 'is-complete': bagian.selesai }">
                    <div class="lesson-completion-copy">
                      <strong>{{ bagian.selesai ? 'Submateri ini sudah selesai' : 'Selesai membaca sub-bab?' }}</strong>
                      <p>
                        {{ bagian.selesai
                          ? 'Progress submateri ini sudah tersimpan.'
                          : 'Tandai untuk memperbarui peta kemampuan dan progress belajarmu.' }}
                      </p>
                    </div>
                    <button
                      v-if="!bagian.selesai"
                      type="button"
                      class="btn btn-amber completion-button"
                      :disabled="submateriMenandai !== null"
                      @click="tandaiSubmateriSelesai(bagian)"
                    >
                      {{ submateriMenandai === bagian.id ? 'Menyimpan...' : '✓  Tandai sebagai Selesai' }}
                    </button>
                    <span v-else class="complete-note">✓ Selesai</span>
                  </div>
                </article>

                <div v-if="detail.fileUrl" class="file-link-wrap">
                  <a :href="detail.fileUrl" target="_blank" rel="noopener" class="file-link">
                    Buka / unduh file materi ↗
                  </a>
                </div>
                <section id="latihan-materi" class="lesson-final-practice" aria-label="Latihan materi">
                  <h2>{{ detail.quizNama || ('Latihan ' + detail.judul) }}</h2>
                  <p class="final-practice-description">{{ detail.quizDeskripsi || 'Uji pemahamanmu sebelum melanjutkan ke materi berikutnya. Hasil latihan membantu memperbarui peta kompetensimu.' }}</p>
                  <div class="final-practice-stats">
                    <div class="final-stat"><strong>{{ detail.jumlahSoalLatihan ?? '—' }}</strong><span>Soal</span></div>
                    <div class="final-stat"><strong>Tanpa batas</strong><span>Waktu pengerjaan</span></div>
                    <div v-if="detail.nilaiTerbaik != null" class="final-stat"><strong>{{ Math.round(detail.nilaiTerbaik) }}</strong><span>Nilai terbaik</span></div>
                  </div>
                  <button type="button" class="btn final-practice-button" :disabled="!detail.quizId || !detail.latihanTersedia" @click="mulaiLatihan(detail)">
                    {{ detail.quizId && detail.latihanTersedia ? 'Mulai latihan' : 'Latihan belum tersedia' }}
                  </button>
                  <p class="final-practice-note">{{ semuaSubmateriSelesai ? '✓ Semua submateri sudah selesai dibaca.' : 'Tandai setiap submateri sebagai selesai untuk memperbarui progress belajarmu.' }}</p>
                </section>
              </div>

              <aside class="lesson-sidebar">
                <nav class="lesson-toc" aria-label="Daftar isi materi">
                  <h3 class="lesson-toc-title">DAFTAR ISI MATERI</h3>
                  <div class="lesson-toc-list">
                    <button
                      v-for="(bagian, index) in submateri"
                      :key="'toc-' + bagian.id"
                      type="button" class="lesson-toc-item"
                      :class="{ 'is-active': bagian.id === submateriAktifId, 'is-complete': bagian.selesai }"
                      :aria-current="bagian.id === submateriAktifId ? 'location' : undefined"
                      @click="lompatKeSubmateri(bagian.id)"
                    >
                      <span class="lesson-toc-index" :class="{ 'is-complete': bagian.selesai }">{{ bagian.selesai ? '✓' : index + 1 }}</span>
                      <span class="lesson-toc-label">{{ bagian.judul }}</span>
                    </button>
                    <button type="button" class="lesson-toc-item lesson-toc-practice"
                      :class="{ 'is-active': submateriAktifId === 'latihan' }"
                      :aria-current="submateriAktifId === 'latihan' ? 'location' : undefined"
                      @click="lompatKeSubmateri('latihan')">
                      <span class="lesson-toc-star" aria-hidden="true">★</span>
                      <span class="lesson-toc-label">{{ detail.quizNama || ('Latihan ' + detail.judul) }}</span>
                    </button>
                  </div>
                </nav>
              </aside>
            </div>

            <div v-else class="lesson-empty">
              <strong>Konten submateri belum tersedia</strong>
              <p>Materi ini belum memiliki bagian pembelajaran yang dapat ditampilkan.</p>
            </div>
          </template>
        </div>
      </section>

      <template v-else>
        <!-- 01 MATERI BELAJAR -->
        <section class="section-block">
          <p class="section-kicker">01 · MATERI BELAJAR</p>
          <h2>Semua Materi</h2>
          <p class="section-desc">Modul dan video per topik, lengkap dengan progress belajarmu.</p>

          <div class="toolbar">
            <label class="search-box">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
              <input v-model="pencarian" type="search" placeholder="Cari materi atau topik..." />
            </label>
            <div class="filters">
              <label>
                <span class="sr-only">Tingkatan</span>
                <select v-model="filterTingkat">
                  <option v-for="option in tingkatOptions" :key="option.value" :value="option.value">
                    Tingkatan: {{ option.label }}
                  </option>
                </select>
              </label>
              <label>
                <span class="sr-only">Jenis materi</span>
                <select v-model="filterJenis">
                  <option value="semua">Jenis Materi: Semua</option>
                  <option value="modul">Jenis Materi: Modul</option>
                  <option value="video" disabled>Jenis Materi: Video (belum tersedia)</option>
                </select>
              </label>
              <label>
                <span class="sr-only">Status materi</span>
                <select v-model="filterStatus">
                  <option value="semua">Status: Semua</option>
                  <option value="belum">Belum dimulai</option>
                  <option value="berjalan">Sedang berjalan</option>
                  <option value="selesai">Selesai</option>
                </select>
              </label>
            </div>
          </div>

          <div v-if="memuat" class="card-grid">
            <div v-for="n in 6" :key="n" class="material-card loading-card">
              <span class="skeleton sk-pill"></span>
              <span class="skeleton sk-title"></span>
              <span class="skeleton sk-copy"></span>
              <span class="skeleton sk-progress"></span>
              <span class="skeleton sk-footer"></span>
            </div>
          </div>
          <div v-else-if="errorKatalog" class="empty-panel">
            <h3>Materi belum dapat dimuat</h3>
            <p>Periksa koneksi internetmu, lalu coba muat ulang daftar materi.</p>
            <button type="button" class="btn btn-primary" @click="muatKatalog">Coba Lagi</button>
          </div>
          <div v-else-if="materiTersaring.length" class="card-grid">
            <article v-for="m in materiTersaring" :key="m.id" class="material-card">
              <div class="material-topline">
                <span class="pill" :class="m.tingkatNama.toLowerCase().includes('provinsi') ? 'pill-yellow' : 'pill-green'">
                  {{ m.tingkatNama }}
                </span>
                <span class="material-type">
                  <svg v-if="jenisMateri(m) === 'Modul'" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 2.5h7l3 3v8H3z" /><path d="M10 2.5v3h3M5.5 8h5M5.5 10.5h5" /></svg>
                  <svg v-else viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" /><path d="m6.5 5.5 4 2.5-4 2.5z" /></svg>
                  {{ jenisMateri(m) }}
                </span>
              </div>

              <div class="material-heading">
                <h3>{{ m.judul }}</h3>
                <span v-if="m.wajib" class="required-tag">Wajib</span>
              </div>
              <p class="material-description">{{ m.deskripsi || 'Pelajari topik ini untuk memperkuat pemahamanmu.' }}</p>

              <div class="progress-track">
                <div
                  class="progress-fill"
                  :class="statusMateri(m)"
                  :style="{ width: `${persentase(m)}%` }"
                ></div>
              </div>
              <div class="material-footer">
                <span :class="'status-' + statusMateri(m)">{{ statusLabel(m) }}</span>
                <button type="button" class="text-action" @click="buka(m.id)">
                  {{ statusMateri(m) === 'selesai' ? 'Baca ulang' : statusMateri(m) === 'belajar' ? 'Lanjutkan' : 'Mulai belajar' }} →
                </button>
              </div>
            </article>
          </div>
          <div v-else class="empty-panel">
            <h3>Tidak ada materi yang cocok</h3>
            <p>Coba ubah kata pencarian atau filter yang kamu pilih.</p>
            <button
              type="button"
              class="btn btn-outline"
              @click="resetFilter"
            >
              Reset Filter
            </button>
          </div>
        </section>

        <div class="section-divider"></div>

        <!-- 02 PAKET LATIHAN -->
        <section class="section-block">
          <p class="section-kicker">02 · LATIHAN SOAL</p>
          <h2>Paket Latihan Soal</h2>
          <p class="section-desc">Kerjakan latihan per topik untuk menguatkan pemahaman.</p>

          <div class="practice-panel">
            <div v-if="memuat" class="practice-loading">
              <span v-for="n in 4" :key="n" class="skeleton practice-skeleton"></span>
            </div>
            <template v-else-if="paketLatihan.length">
              <div v-for="m in paketLatihan" :key="m.id" class="practice-row">
                <div class="practice-info">
                  <div class="practice-title">
                    <strong>{{ m.judul }}</strong>
                    <span class="difficulty" :class="m.wajib ? 'difficulty-high' : 'difficulty-normal'">
                      {{ m.wajib ? 'Prioritas' : 'Latihan' }}
                    </span>
                  </div>
                  <p>{{ m.wajib ? `Materi wajib · Prioritas ${m.prioritas ?? '—'}` : 'Latihan untuk memperkuat pemahaman topik ini' }}</p>
                </div>
                <div class="practice-score">
                  <strong>{{ m.nilaiTerbaik == null ? '—' : Math.round(m.nilaiTerbaik) }}</strong>
                  <span>{{ labelNilai(m) }}</span>
                </div>
                <button
                  type="button"
                  class="btn"
                  :class="m.nilaiTerbaik == null ? 'btn-green' : 'btn-amber'"
                  @click="mulaiLatihan(m)"
                >
                  {{ m.nilaiTerbaik == null ? 'Mulai' : 'Ulangi' }}
                </button>
              </div>
            </template>
            <div v-else class="practice-empty">
              <p>Belum ada paket latihan yang tersedia pada materi ini.</p>
            </div>
            <div class="practice-bottom">
              <span>{{ paketLatihan.length }} paket latihan tersedia</span>
              <button type="button" class="btn btn-amber" @click="router.push({ name: 'siswa.riwayat' })">
                Lihat Riwayat →
              </button>
            </div>
          </div>
        </section>

        <div class="section-divider"></div>

        <!-- 03 REKOMENDASI -->
        <section class="section-block recommendation-section">
          <p class="section-kicker">03 · REKOMENDASI MATERI</p>
          <h2>Rekomendasi Untukmu</h2>
          <p class="section-desc">Disusun otomatis setelah kamu menyelesaikan pre-test pemetaan kompetensi.</p>

          <div class="recommendation-status">
            <span>Status Siswa: <strong>{{ rekomendasiStatus }}</strong></span>
            <span v-if="sudahPretest" class="recommendation-pill">Berdasarkan hasil pre-test terakhir</span>
          </div>

          <div v-if="!sudahPretest" class="recommendation-empty">
            <div class="empty-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="3.5" width="12" height="17" rx="2" /><path d="M9 8h6M9 11.5h6M9 15h3" /><path d="M9 2.5h6" /></svg>
            </div>
            <h3>{{ pretest.status === STATUS.MENILAI ? 'Hasil pre-test sedang dianalisis' : 'Belum ada rekomendasi materi' }}</h3>
            <p>
              {{ pretest.status === STATUS.MENILAI
                ? 'Rekomendasi akan muncul otomatis setelah hasil pemetaan kompetensimu selesai dihitung.'
                : 'Kerjakan pre-test terlebih dahulu agar kami dapat memetakan kompetensimu dan memberikan rekomendasi materi yang sesuai.' }}
            </p>
            <div class="empty-actions">
              <button type="button" class="btn btn-amber" @click="bukaPretest">
                {{ pretest.status === STATUS.MENILAI ? 'Lihat Proses Pre-Test' : 'Kerjakan Pre-Test' }} →
              </button>
              <button type="button" class="btn btn-outline" @click="resetFilter">
                Lihat Semua Materi
              </button>
            </div>
          </div>

          <div v-else-if="daftarRekomendasi.length" class="recommendation-list">
            <article v-for="m in daftarRekomendasi" :key="m.id" class="recommendation-row">
              <div class="recommendation-copy">
                <div class="recommendation-title">
                  <strong>{{ m.judul }}</strong>
                  <span class="priority-label" :class="m.prioritas === 1 ? 'priority-high' : 'priority-next'">
                    {{ m.prioritas === 1 ? 'Prioritas tinggi' : 'Lanjutkan' }}
                  </span>
                </div>
                <p v-if="m.nilaiPemetaan != null">
                  Direkomendasikan karena skor kompetensi pada topik ini {{ Math.round(m.nilaiPemetaan) }}%.
                  {{ m.prioritas === 1 ? 'Topik ini menjadi prioritas belajarmu saat ini.' : 'Pelajari setelah menyelesaikan prioritas utama.' }}
                </p>
                <p v-else>
                  Materi ini termasuk rekomendasi dari hasil pre-test. Pelajari untuk memperkuat kompetensimu.
                </p>
                <span class="recommendation-progress" v-if="m.progress?.status === 'selesai'">Materi sudah selesai dipelajari</span>
                <span class="recommendation-progress" v-else-if="m.progress?.status === 'belajar'">Progress belajar {{ persentase(m) }}%</span>
              </div>
              <button
                type="button"
                class="btn"
                :class="m.prioritas === 1 ? 'btn-green' : 'btn-amber'"
                @click="buka(m.id)"
              >
                {{ statusMateri(m) === 'selesai' ? 'Baca ulang' : statusMateri(m) === 'belajar' ? 'Lanjutkan' : 'Mulai' }}
              </button>
            </article>
          </div>
          <div v-else class="recommendation-empty compact-empty">
            <h3>Semua rekomendasi sudah terpenuhi</h3>
            <p>Tidak ada materi prioritas tambahan dari hasil pre-test terakhir.</p>
            <button type="button" class="btn btn-outline" @click="resetFilter">
              Lihat Semua Materi
            </button>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<style scoped>
.materi-page {
  min-height: 100%;
  background: #f5f7fb;
  color: #17243b;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.page-header {
  min-height: 76px;
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  background: #fff;
  border-bottom: 1px solid #e7ecf3;
}
.header-copy h1 {
  margin: 0;
  color: #132342;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 20px;
  line-height: 1.3;
  font-weight: 700;
  letter-spacing: -.025em;
}
.header-copy p { margin: 4px 0 0; color: #718096; font-size: 13px; line-height: 1.5; }
.materi-main { width: 100%; max-width: 1480px; margin: 0 auto; padding: 30px 32px 56px; }
.section-block { margin: 0; }
.section-kicker {
  margin: 0 0 7px;
  color: #c47a12;
  font-size: 11px;
  line-height: 1.4;
  font-weight: 800;
  letter-spacing: .075em;
}
.section-block > h2, .detail-title-row h2 {
  margin: 0;
  color: #142440;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 25px;
  line-height: 1.25;
  font-weight: 700;
  letter-spacing: -.035em;
}
.section-desc { margin: 7px 0 22px; color: #718096; font-size: 14px; line-height: 1.6; }
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin: 0 0 20px;
}
.search-box {
  width: min(340px, 100%);
  height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 14px;
  border: 1px solid #dfe6ef;
  border-radius: 10px;
  background: #fff;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.search-box:focus-within { border-color: #8ba9d3; box-shadow: 0 0 0 3px rgb(39 94 160 / 8%); }
.search-box svg { width: 17px; height: 17px; flex: none; fill: none; stroke: #8796aa; stroke-width: 1.8; }
.search-box input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: #24334d; font: inherit; font-size: 13px; }
.search-box input::placeholder { color: #9aa7b8; }
.filters { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 9px; }
.filters select {
  max-width: 190px;
  height: 42px;
  padding: 0 30px 0 12px;
  border: 1px solid #dfe6ef;
  border-radius: 10px;
  background: #fff;
  color: #52627a;
  font: inherit;
  font-size: 12px;
  outline: none;
}
.filters select:focus { border-color: #8ba9d3; }
.card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.material-card {
  min-width: 0;
  min-height: 190px;
  padding: 20px 20px 17px;
  display: flex;
  flex-direction: column;
  border: 1px solid #e3e9f1;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 3px 12px rgb(23 42 77 / 3%);
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.material-card:hover { border-color: #cbd8e8; box-shadow: 0 8px 22px rgb(23 42 77 / 7%); transform: translateY(-2px); }
.material-topline, .material-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.pill { display: inline-flex; align-items: center; width: fit-content; padding: 5px 9px; border-radius: 999px; font-size: 10px; line-height: 1.2; font-weight: 700; }
.pill-green { background: #e7f7ef; color: #16865b; }
.pill-yellow { background: #fff3d4; color: #a86a00; }
.pill-amber { background: #fff3d4; color: #a76600; }
.material-type { display: inline-flex; align-items: center; gap: 6px; color: #8492a6; font-size: 11px; }
.material-type svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.35; }
.material-heading { display: flex; align-items: center; gap: 8px; margin-top: 15px; }
.material-heading h3 { min-width: 0; margin: 0; overflow: hidden; color: #1b2a45; font-size: 16px; line-height: 1.4; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.required-tag { padding: 3px 7px; border-radius: 5px; background: #fff4dc; color: #a76600; font-size: 10px; font-weight: 700; }
.material-description { min-height: 38px; margin: 7px 0 15px; overflow: hidden; color: #758399; font-size: 12px; line-height: 1.55; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.progress-track { width: 100%; height: 6px; margin-top: auto; overflow: hidden; border-radius: 999px; background: #edf1f6; }
.progress-fill { height: 100%; border-radius: inherit; background: #e8a22c; transition: width .25s ease; }
.progress-fill.selesai { background: #18a675; }
.progress-fill.belum, .progress-fill.belajar { background: #e8a22c; }
.material-footer { margin-top: 12px; }
.material-footer > span { font-size: 11px; font-weight: 650; }
.status-selesai { color: #10966a; }
.status-belajar { color: #bd7a14; }
.status-belum { color: #8492a6; }
.text-action { padding: 0; border: 0; background: transparent; color: #244b7c; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
.text-action:hover, .back-link:hover { color: #c47a12; }
.section-divider { height: 1px; margin: 28px 0 30px; background: #e3e9f1; }
.practice-panel, .recommendation-list, .recommendation-empty, .empty-panel, .detail-panel {
  border: 1px solid #e3e9f1;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 3px 12px rgb(23 42 77 / 3%);
}
.practice-panel { padding: 18px 22px 16px; }
.practice-row {
  min-height: 76px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 110px 94px;
  align-items: center;
  gap: 18px;
  padding: 12px 14px;
  border-bottom: 1px solid #edf1f6;
}
.practice-info { min-width: 0; }
.practice-title { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; }
.practice-title strong { color: #1d2b43; font-size: 14px; font-weight: 700; }
.difficulty { padding: 4px 8px; border-radius: 5px; font-size: 10px; font-weight: 750; }
.difficulty-high { background: #fff0d3; color: #a96700; }
.difficulty-normal { background: #e9f8f1; color: #18865d; }
.practice-info p { margin: 5px 0 0; color: #8492a6; font-size: 12px; }
.practice-score { display: flex; flex-direction: column; align-items: center; }
.practice-score strong { color: #17243a; font-size: 18px; font-weight: 800; }
.practice-score span { color: #96a2b3; font-size: 10px; text-align: center; }
.btn { min-height: 38px; padding: 0 17px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: 9px; font: inherit; font-size: 12px; font-weight: 700; white-space: nowrap; cursor: pointer; transition: filter .15s ease, transform .15s ease, box-shadow .15s ease; }
.btn:hover { filter: brightness(.98); transform: translateY(-1px); }
.btn:focus-visible { outline: 3px solid rgb(49 105 178 / 22%); outline-offset: 2px; }
.btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.btn-amber { background: #e9a12a; color: #fff; }
.btn-green { background: #24976b; color: #fff; }
.btn-primary { background: #153866; color: #fff; }
.btn-primary:hover { background: #1d4b82; }
.btn-outline { border-color: #dce4ee; background: #fff; color: #42516a; }
.practice-bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 8px 0; }
.practice-bottom > span { color: #8b99ab; font-size: 11px; }
.practice-empty { padding: 28px 8px; text-align: center; color: #8492a6; font-size: 13px; }
.recommendation-section { padding-bottom: 12px; }
.recommendation-status { min-height: 42px; margin: 0 0 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #8492a6; font-size: 12px; }
.recommendation-status strong { color: #52627a; font-weight: 700; }
.recommendation-pill { padding: 6px 10px; border-radius: 7px; background: #edf2f8; color: #52627a; font-size: 11px; }
.recommendation-empty { min-height: 220px; padding: 28px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.empty-icon { width: 48px; height: 48px; margin-bottom: 14px; display: grid; place-items: center; border: 1px solid #dbeafe; border-radius: 14px; background: #f2f7ff; }
.empty-icon svg { width: 22px; height: 22px; fill: none; stroke: #3478ee; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.recommendation-empty h3 { margin: 0; color: #1b2940; font-size: 16px; font-weight: 750; }
.recommendation-empty p { max-width: 520px; margin: 8px 0 17px; color: #7c8ba2; font-size: 13px; line-height: 1.6; }
.empty-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
.recommendation-list { padding: 8px 20px; }
.recommendation-row { min-height: 86px; display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 14px 12px; border-bottom: 1px solid #edf1f6; }
.recommendation-row:last-child { border-bottom: 0; }
.recommendation-copy { min-width: 0; }
.recommendation-title { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; }
.recommendation-title strong { color: #1c2940; font-size: 14px; font-weight: 700; }
.priority-label { padding: 4px 8px; border-radius: 5px; font-size: 10px; font-weight: 750; }
.priority-high { background: #ffe8eb; color: #c83e55; }
.priority-next { background: #fff2d3; color: #a96b00; }
.recommendation-copy p { margin: 5px 0 0; color: #718198; font-size: 12px; line-height: 1.55; }
.recommendation-progress { display: inline-block; margin-top: 5px; color: #8998ad; font-size: 11px; }
.empty-panel { padding: 32px; text-align: center; }
.empty-panel h3 { margin: 0; color: #1b2940; font-size: 16px; }
.empty-panel p { margin: 8px 0 16px; color: #7c8ba2; font-size: 13px; }
.detail-panel { padding: 30px 34px 36px; }
.back-link { padding: 0; border: 0; background: transparent; color: #3268a8; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
.detail-title-row { margin-top: 24px; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.detail-title-row h2 { margin-top: 7px; font-size: 30px; }
.detail-title-row p { max-width: 760px; margin: 8px 0 0; color: #718096; font-size: 14px; line-height: 1.65; }
.detail-image { display: block; max-width: 100%; max-height: 390px; margin: 24px auto 0; border-radius: 14px; object-fit: contain; }
.learning-progress { margin-top: 24px; padding: 18px 20px; border: 1px solid #e3eaf2; border-radius: 14px; background: #f8fafc; }
.learning-progress-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.learning-progress-head > div { display: flex; flex-direction: column; gap: 5px; }
.learning-progress-head strong { color: #24334d; font-size: 14px; font-weight: 750; }
.learning-progress-head span { color: #8795aa; font-size: 12px; }
.learning-progress-head > strong { color: #bd7a14; font-size: 20px; }
.learning-progress-track { height: 8px; margin-top: 13px; overflow: hidden; border-radius: 999px; background: #e5ebf2; }
.learning-progress-track .progress-fill { background: #18a675; }
.lesson-layout {
  display: grid;
  grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  align-items: start;
  gap: 20px;
  margin-top: 26px;
}
.lesson-toc {
  position: sticky;
  top: 22px;
  min-width: 0;
  padding: 19px 14px 14px;
  border: 1px solid #e3e9f1;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 3px 12px rgb(23 42 77 / 3%);
}
.lesson-toc-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 5px 14px;
  border-bottom: 1px solid #edf1f6;
}
.lesson-toc-eyebrow {
  display: block;
  margin-bottom: 4px;
  color: #a0aabd;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: .09em;
}
.lesson-toc-heading h3 {
  margin: 0;
  color: #172943;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 16px;
  font-weight: 700;
}
.lesson-toc-count {
  flex: none;
  padding: 5px 7px;
  border-radius: 7px;
  background: #f0f4f9;
  color: #718096;
  font-size: 10px;
  font-weight: 700;
}
.lesson-toc-list { display: grid; gap: 4px; padding-top: 9px; }
.lesson-toc-item {
  width: 100%;
  min-width: 0;
  min-height: 42px;
  padding: 8px 7px;
  display: flex;
  align-items: center;
  gap: 9px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #586981;
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition: background .15s ease, color .15s ease;
}
.lesson-toc-item:hover, .lesson-toc-item:focus-visible {
  outline: none;
  background: #f1f5fa;
  color: #1d4b7d;
}
.lesson-toc-index {
  flex: none;
  color: #9aa7b8;
  font-size: 10px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
.lesson-toc-label {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  font-size: 11px;
  line-height: 1.45;
  font-weight: 650;
  text-overflow: ellipsis;
}
.lesson-toc-state {
  flex: none;
  color: #b5bfcc;
  font-size: 12px;
  font-weight: 800;
}
.lesson-toc-state.is-complete { color: #16865b; }
.lesson-reader {
  min-width: 0;
  padding: 0 30px;
  border: 1px solid #e3e9f1;
  border-radius: 18px;
  background: #fff;
}
.lesson-section { min-width: 0; padding: 30px 0 28px; scroll-margin-top: 24px; }
.lesson-section + .lesson-section { border-top: 1px solid #e8edf4; }
.lesson-section-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.lesson-section-title { min-width: 0; display: flex; align-items: center; gap: 14px; }
.lesson-section-number {
  width: 46px;
  height: 46px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 13px;
  background: #edf4fc;
  color: #244f82;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 16px;
  font-weight: 700;
}
.lesson-section-heading-copy { min-width: 0; }
.lesson-section-eyebrow { display: block; margin-bottom: 4px; color: #a0aabd; font-size: 10px; font-weight: 800; letter-spacing: .09em; }
.lesson-section h3 { margin: 0; color: #172943; font-family: "Space Grotesk", Inter, sans-serif; font-size: 21px; line-height: 1.35; font-weight: 700; letter-spacing: -.02em; }
.lesson-status { display: inline-flex; align-items: center; gap: 6px; flex: none; padding: 6px 10px; border-radius: 999px; background: #fff5df; color: #a66b0a; font-size: 11px; font-weight: 700; }
.lesson-status.is-complete { background: #e8f7ef; color: #16865b; }
.lesson-content-body { margin-top: 22px; color: #34445d; font-size: 15px; line-height: 1.85; overflow-wrap: anywhere; }
.lesson-content-body :deep(img) { max-width: 100%; height: auto; border-radius: 10px; }
.lesson-content-body :deep(table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
.lesson-content-body :deep(th), .lesson-content-body :deep(td) { padding: 9px 12px; border: 1px solid #dfe6ef; text-align: left; }
.lesson-content-body :deep(th) { background: #f5f8fc; color: #24334d; font-weight: 700; }
.submateri-empty { margin: 22px 0 0; color: #8795aa; font-size: 13px; }
.lesson-completion {
  margin-top: 26px;
  padding: 16px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  border: 1px solid #e4eaf2;
  border-radius: 13px;
  background: #f8fafc;
}
.lesson-completion.is-complete { border-color: #d5eee1; background: #f3fbf6; }
.lesson-completion-copy { min-width: 0; display: flex; align-items: center; gap: 12px; }
.lesson-completion-icon {
  width: 34px;
  height: 34px;
  flex: none;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #eaf1fa;
  color: #315d91;
  font-size: 16px;
  font-weight: 800;
}
.lesson-completion.is-complete .lesson-completion-icon { background: #dff4e8; color: #16865b; }
.lesson-completion-copy strong { display: block; color: #24334d; font-size: 13px; font-weight: 750; }
.lesson-completion-copy p { margin: 3px 0 0; color: #8290a4; font-size: 11px; line-height: 1.5; }
.completion-button { min-width: 150px; min-height: 42px; }
.file-link-wrap { margin-top: 20px; }
.file-link { color: #245b9a; font-size: 13px; font-weight: 700; }
.detail-actions { margin-top: 22px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.complete-note { color: #16865b; font-size: 12px; font-weight: 750; }
.lesson-empty { margin-top: 26px; padding: 34px; border: 1px dashed #cbd6e4; border-radius: 16px; background: #fbfcfe; text-align: center; }
.lesson-empty strong { color: #263750; font-size: 15px; }
.lesson-empty p { margin: 7px 0 0; color: #8795aa; font-size: 13px; }
.detail-loading { padding: 52px 0; color: #8795aa; font-size: 14px; text-align: center; }
.loading-card { gap: 12px; }
.skeleton { display: block; border-radius: 6px; background: linear-gradient(90deg, #e8edf4 25%, #f4f6f9 45%, #e8edf4 65%); background-size: 220% 100%; animation: shimmer 1.35s ease-in-out infinite; }
@keyframes shimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
.sk-pill { width: 88px; height: 22px; border-radius: 999px; }
.sk-title { width: 52%; height: 18px; margin-top: 4px; }
.sk-copy { width: 82%; height: 12px; }
.sk-progress { width: 100%; height: 6px; margin-top: auto; }
.sk-footer { width: 100%; height: 12px; }
.practice-loading { display: grid; gap: 12px; }
.practice-skeleton { height: 48px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

@media (max-width: 1100px) {
  .materi-main { padding-inline: 24px; }
  .toolbar { align-items: stretch; flex-direction: column; }
  .search-box { width: 100%; }
  .filters { justify-content: flex-start; }
  .card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 1100px) {
  .lesson-layout { grid-template-columns: minmax(190px, 220px) minmax(0, 1fr); gap: 14px; }
  .lesson-reader { padding-inline: 22px; }
}
@media (max-width: 760px) {
  .page-header { padding: 14px 18px; }
  .lesson-layout { grid-template-columns: minmax(0, 1fr); }
  .lesson-toc { position: static; padding: 15px 13px 12px; }
  .lesson-toc-list {
    display: flex;
    gap: 7px;
    overflow-x: auto;
    padding: 10px 1px 3px;
    scrollbar-width: thin;
  }
  .lesson-toc-item {
    width: max-content;
    max-width: 230px;
    min-width: 150px;
    flex: 0 0 auto;
    border: 1px solid #e8edf4;
    background: #fbfcfe;
  }
  .lesson-toc-label { white-space: nowrap; }
  .materi-main { padding: 22px 18px 40px; }
  .detail-panel { padding: 22px 20px 26px; }
  .detail-title-row { flex-direction: column; }
  .lesson-reader { padding-inline: 20px; }
  .lesson-section { padding: 24px 0; }
  .lesson-section h3 { font-size: 18px; }
  .lesson-completion { align-items: flex-start; flex-direction: column; }
  .completion-button { width: 100%; }
}
@media (max-width: 600px) {
  .header-copy p { max-width: 235px; }
  .lesson-reader { padding-inline: 16px; }
  .materi-main { padding: 18px 14px 32px; }
  .section-block > h2, .detail-title-row h2 { font-size: 22px; }
  .card-grid { grid-template-columns: 1fr; }
  .material-card { min-height: 178px; }
  .practice-panel { padding: 10px; }
  .practice-row { grid-template-columns: minmax(0, 1fr) 56px; gap: 10px; padding-inline: 6px; }
  .practice-row > .btn { grid-column: 1 / -1; justify-self: end; }
  .recommendation-row { align-items: flex-start; flex-direction: column; gap: 12px; }
  .recommendation-row > .btn { align-self: flex-end; }
  .recommendation-status { align-items: flex-start; flex-direction: column; padding: 6px 0; }
  .lesson-section-header { align-items: flex-start; flex-direction: column; gap: 12px; }
  .lesson-section-title { align-items: flex-start; gap: 11px; }
  .lesson-section-number { width: 40px; height: 40px; border-radius: 11px; }
  .lesson-section h3 { font-size: 17px; }
  .lesson-content-body { margin-top: 17px; font-size: 14px; }
  .lesson-completion { padding: 14px; }
}

/* Detail materi: layout referensi — bacaan kiri, daftar isi dan latihan kanan. */
.detail-view { min-width: 0; }
.detail-breadcrumb {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  margin: 0 0 16px 2px;
  color: #526783;
  font-size: 12px;
  font-weight: 600;
}
.detail-breadcrumb span { color: #a1adbd; }
.detail-panel { padding: 30px 32px 38px; }
.detail-overview {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(245px, 300px);
  align-items: end;
  gap: 30px;
  margin-bottom: 38px;
}
.detail-overview-copy { min-width: 0; }
.detail-badges { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 15px; }
.pill-blue { background: #edf2ff; color: #4356c7; }
.detail-overview h2 {
  margin: 0;
  color: #102d53;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 29px;
  line-height: 1.25;
  font-weight: 750;
  letter-spacing: -.035em;
}
.detail-overview-copy > p { margin: 9px 0 0; color: #718096; font-size: 13px; line-height: 1.6; }
.detail-progress { min-width: 0; padding-bottom: 4px; }
.detail-progress-label { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 13px; color: #64748b; font-size: 13px; }
.detail-progress-label strong { color: #102d53; font-size: 13px; white-space: nowrap; }
.detail-progress .learning-progress-track { margin: 0; height: 7px; background: #e3e9f1; }
.detail-progress .progress-fill { background: #f59e0b; }
.detail-progress-meta { display: block; margin-top: 7px; color: #93a0b2; font-size: 10px; text-align: right; }
.detail-image { margin: 0 auto 26px; }
.lesson-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(250px, 276px);
  align-items: start;
  gap: 28px;
  margin-top: 0;
}
.lesson-reader {
  min-width: 0;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}
.lesson-section { padding: 0 0 27px; scroll-margin-top: 24px; }
.lesson-section + .lesson-section { padding-top: 27px; border-top: 1px solid #e8edf4; }
.lesson-section-header { align-items: center; gap: 12px; }
.lesson-section-title { display: block; }
.lesson-section-number {
  display: inline;
  width: auto;
  height: auto;
  margin-right: 5px;
  border-radius: 0;
  background: transparent;
  color: #102d53;
  font: inherit;
  font-size: 20px;
}
.lesson-section-heading-copy { display: inline; }
.lesson-section-eyebrow { display: none; }
.lesson-section h3 {
  display: inline;
  color: #102d53;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 20px;
  line-height: 1.4;
  font-weight: 700;
}
.lesson-status { padding: 5px 8px; font-size: 10px; }
.lesson-content-body { margin-top: 17px; color: #263a56; font-size: 14px; line-height: 1.8; }
.lesson-content-body :deep(h1),
.lesson-content-body :deep(h2),
.lesson-content-body :deep(h3) { color: #102d53; }
.lesson-completion {
  margin-top: 27px;
  padding: 17px 0 0;
  border: 0;
  border-top: 1px solid #e5ebf2;
  border-radius: 0;
  background: transparent;
}
.lesson-completion.is-complete { border-color: #d5eee1; background: transparent; }
.lesson-completion-copy { align-items: flex-start; gap: 0; }
.lesson-completion-copy strong { color: #263750; font-size: 12px; }
.lesson-completion-copy p { max-width: 420px; font-size: 11px; }
.completion-button { min-width: 0; min-height: 40px; padding-inline: 16px; background: #f59e0b; color: #fff; }
.completion-button:hover { background: #e89405; }
.lesson-sidebar { min-width: 0; display: grid; gap: 18px; }
.lesson-toc {
  position: sticky;
  top: 20px;
  padding: 24px 18px 20px;
  border: 1px solid #e5ebf3;
  border-radius: 15px;
  background: #fff;
  box-shadow: none;
}
.lesson-toc-title {
  margin: 0 0 12px;
  padding: 0 4px 13px;
  border-bottom: 1px solid #edf1f6;
  color: #34445d;
  font-family: "Space Grotesk", Inter, sans-serif;
  font-size: 14px;
  font-weight: 750;
}
.lesson-toc-heading { display: none; }
.lesson-toc-list { display: grid; gap: 3px; padding-top: 0; }
.lesson-toc-item {
  min-height: 38px;
  padding: 7px 8px;
  gap: 9px;
  color: #243650;
}
.lesson-toc-item.is-active { background: #e5efff; color: #1557d5; }
.lesson-toc-index {
  width: 20px;
  height: 20px;
  display: inline-grid;
  place-items: center;
  border: 1px solid #dce5f1;
  border-radius: 50%;
  background: #fff;
  color: #8292a8;
  font-size: 10px;
}
.lesson-toc-index.is-complete {
  border-color: #12a365;
  background: #12a365;
  color: #fff;
}
.lesson-toc-label { font-size: 12px; font-weight: 600; }
.lesson-practice-card {
  padding: 18px 17px 16px;
  border-radius: 14px;
  background: #102f55;
  color: #fff;
  box-shadow: 0 4px 12px rgb(16 47 85 / 12%);
}
.lesson-practice-topline, .lesson-practice-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.lesson-practice-topline { color: #c4d2e4; font-size: 10px; }
.lesson-practice-level {
  padding: 4px 8px;
  border: 1px solid rgb(245 158 11 / 45%);
  border-radius: 999px;
  color: #fbbf24;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
}
.lesson-practice-card h3 { margin: 12px 0 6px; color: #fff; font-size: 14px; font-weight: 750; }
.lesson-practice-card p { margin: 0; color: #c2d0e1; font-size: 11px; line-height: 1.55; }
.lesson-practice-footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid rgb(255 255 255 / 12%); }
.lesson-practice-footer > span { color: #c2d0e1; font-size: 10px; }
.lesson-practice-card .btn { min-height: 34px; padding-inline: 13px; background: #f59e0b; color: #fff; font-size: 10px; }
.detail-view .lesson-empty { margin-top: 0; }

@media (max-width: 1050px) {
  .detail-panel { padding: 26px 24px 32px; }
  .lesson-layout { grid-template-columns: minmax(0, 1fr) 250px; gap: 20px; }
  .detail-overview { grid-template-columns: minmax(0, 1fr) minmax(220px, 270px); gap: 20px; }
}
@media (max-width: 760px) {
  .detail-panel { padding: 22px 18px 28px; }
  .detail-overview { grid-template-columns: minmax(0, 1fr); gap: 18px; margin-bottom: 26px; }
  .detail-overview h2 { font-size: 24px; }
  .detail-progress { max-width: none; }
  .detail-progress-meta { text-align: left; }
  .lesson-layout { grid-template-columns: minmax(0, 1fr); }
  .lesson-sidebar { grid-row: 1; }
  .lesson-toc { position: static; padding: 17px 14px 14px; }
  .lesson-toc-list { display: flex; gap: 7px; overflow-x: auto; padding: 0 0 3px; scrollbar-width: thin; }
  .lesson-toc-item { width: max-content; max-width: 240px; min-width: 150px; flex: 0 0 auto; border: 1px solid #e8edf4; background: #fbfcfe; }
  .lesson-toc-item.is-active { border-color: #d4e3ff; background: #e5efff; }
  .lesson-section-header { align-items: flex-start; flex-direction: row; }
  .lesson-section h3 { font-size: 18px; }
}
@media (max-width: 600px) {
  .detail-badges { margin-bottom: 11px; }
  .detail-overview h2 { font-size: 22px; }
  .lesson-section-header { align-items: flex-start; flex-direction: column; gap: 8px; }
  .lesson-section h3 { font-size: 17px; }
  .lesson-completion { align-items: flex-start; flex-direction: column; }
  .completion-button { width: 100%; }
}



/* Hanya mode detail materi yang mengunci halaman; katalog materi tetap memakai scroll normal. */
.materi-page.is-detail-open {
  height: 100dvh;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.materi-page.is-detail-open .page-header { flex: 0 0 auto; }
.materi-page.is-detail-open .materi-main {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding-bottom: 20px;
}
.materi-page.is-detail-open .detail-view {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.materi-page.is-detail-open .detail-breadcrumb { flex: 0 0 auto; }
.materi-page.is-detail-open .detail-panel {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.materi-page.is-detail-open .detail-overview,
.materi-page.is-detail-open .detail-image { flex: 0 0 auto; }
.materi-page.is-detail-open .lesson-layout {
  flex: 1 1 auto;
  min-height: 0;
  align-items: stretch;
}
.materi-page.is-detail-open .lesson-reader {
  height: auto;
  min-height: 0;
  max-height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
}
.materi-page.is-detail-open .lesson-sidebar {
  min-height: 0;
  align-self: start;
}
@media (max-width: 760px) {
  .materi-page.is-detail-open .materi-main { padding: 16px 14px; }
  .materi-page.is-detail-open .detail-panel { padding: 16px 14px; }
  .materi-page.is-detail-open .lesson-layout { overflow: hidden; }
  .materi-page.is-detail-open .lesson-reader { height: auto; min-height: 0; }
}

/* Detail materi: scroll konten kiri, daftar isi kanan tetap diam. */
.detail-view .lesson-layout { grid-template-columns: minmax(0, 1fr) 276px; gap: 28px; align-items: start; }
.detail-view .lesson-reader {
  min-width: 0; height: min(72vh, 760px); min-height: 420px; overflow-y: auto;
  overflow-x: hidden; overscroll-behavior: contain; scrollbar-width: thin; padding: 0 12px 0 0;
}
.detail-view .lesson-reader:focus-visible { outline: 2px solid #bfdbfe; outline-offset: 2px; }
.detail-view .lesson-sidebar {
  position: sticky;
  top: 20px;
  align-self: start;
  min-width: 0;
  width: 100%;
  display: block;
}
.detail-view .lesson-toc { position: static; top: auto; width: 100%; margin: 0; padding: 24px 18px; box-sizing: border-box; }
.detail-view .lesson-toc-practice { margin-top: 12px; padding-top: 14px; border-top: 1px solid #e8edf4; border-radius: 0 0 9px 9px; }
.detail-view .lesson-toc-practice.is-active { border-radius: 9px; }
.detail-view .lesson-toc-practice .lesson-toc-label { font-weight: 750; }
.detail-view .lesson-toc-star { display: inline-grid; place-items: center; flex: none; width: 23px; height: 23px; border-radius: 50%; background: #fbbf24; color: #102f55; font-size: 12px; }
.detail-view .lesson-final-practice { min-height: 380px; margin-top: 27px; padding: 30px 28px; border-radius: 15px; background: linear-gradient(120deg, #10214b 0%, #15396f 55%, #2455aa 100%); color: #fff; }
.detail-view .lesson-final-practice h2 { margin: 0 0 14px; color: #fff; font-family: 'Space Grotesk', Inter, sans-serif; font-size: 23px; font-weight: 750; }
.detail-view .final-practice-description { max-width: 650px; margin: 0 0 18px; color: #dae5f5; font-size: 13px; line-height: 1.65; }
.detail-view .final-practice-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.detail-view .final-stat { min-width: 96px; padding: 13px 16px; display: flex; flex-direction: column; align-items: center; gap: 5px; border-radius: 12px; background: rgb(255 255 255 / 12%); }
.detail-view .final-stat strong { color: #fff; font-size: 18px; font-weight: 800; }
.detail-view .final-stat span { color: #c2d3ec; font-size: 11px; }
.detail-view .final-practice-button { min-height: 43px; padding-inline: 22px; border-radius: 10px; background: #fbbf24; color: #102f55; }
.detail-view .final-practice-note { margin: 18px 0 0; color: #b9c9e4; font-size: 11px; }
@media (max-width: 760px) {
  .detail-view .lesson-layout { grid-template-columns: minmax(0, 1fr); gap: 18px; }
  .detail-view .lesson-sidebar { position: static; top: auto; grid-row: 1; }
  .detail-view .lesson-toc-list { display: flex; gap: 7px; overflow-x: auto; }
  .detail-view .lesson-toc-item { width: max-content; min-width: 150px; flex: 0 0 auto; }
  .detail-view .lesson-toc-practice { margin-top: 0; padding-top: 8px; border-top: 0; }
  .detail-view .lesson-reader { height: min(70vh, 650px); min-height: 360px; }
  .detail-view .lesson-final-practice { min-height: 320px; padding: 24px 19px; }
}


/* Daftar isi detail materi dibatasi ke tinggi viewport agar seluruh item dapat diakses
   melalui scroll internal tanpa menggerakkan halaman. */
@media (min-width: 761px) {
  .materi-page.is-detail-open .detail-view .lesson-sidebar {
    min-height: 0;
    max-height: calc(100dvh - 190px);
    align-self: start;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: calc(100dvh - 190px);
    overflow: hidden;
    padding: 18px 14px 14px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-title {
    flex: 0 0 auto;
    margin-bottom: 8px;
    padding-bottom: 10px;
    font-size: 13px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-list {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-gutter: stable;
    gap: 2px;
    padding: 0 2px 2px 0;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-item {
    flex: 0 0 auto;
    min-height: 34px;
    padding: 6px 7px;
    gap: 7px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-label {
    font-size: 11px;
    line-height: 1.35;
    font-weight: 600;
    white-space: normal;
    overflow-wrap: anywhere;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-practice {
    flex: 0 0 auto;
    margin-top: 7px;
    padding-top: 9px;
  }
}
@media (max-width: 760px) {
  .materi-page.is-detail-open .detail-view .lesson-toc {
    max-height: 190px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-title {
    flex: 0 0 auto;
    font-size: 12px;
    padding-bottom: 8px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-list {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 4px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-item {
    min-height: 34px;
    padding: 6px 7px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-label {
    font-size: 11px;
    line-height: 1.3;
  }
}

/* Finalisasi proporsi dua kolom pada detail materi: kedua panel mengikuti
   tinggi ruang yang tersedia, sementara daftar isi dan pembaca scroll sendiri. */
.materi-page.is-detail-open .detail-view .lesson-layout {
  min-height: 0;
  align-items: stretch;
  grid-template-columns: minmax(0, 1fr) minmax(250px, 276px);
  gap: 24px;
}
.materi-page.is-detail-open .detail-view .lesson-reader {
  width: 100%;
  min-width: 0;
  min-height: 0;
  max-height: 100%;
  height: 100%;
  overflow-x: hidden;
  overflow-y: auto;
  box-sizing: border-box;
}
.materi-page.is-detail-open .detail-view .lesson-sidebar {
  min-width: 0;
  min-height: 0;
  height: 100%;
  max-height: 100%;
  align-self: stretch;
  overflow: hidden;
}
.materi-page.is-detail-open .detail-view .lesson-toc {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  min-height: 0;
  max-height: 100%;
  padding: 16px 13px 12px;
  overflow: hidden;
  box-sizing: border-box;
}
.materi-page.is-detail-open .detail-view .lesson-toc-title {
  flex: 0 0 auto;
  margin-bottom: 8px;
  padding-bottom: 9px;
  font-size: 12px;
}
.materi-page.is-detail-open .detail-view .lesson-toc-list {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-gutter: stable;
  gap: 2px;
  padding: 0 3px 3px 0;
}
.materi-page.is-detail-open .detail-view .lesson-toc-item {
  flex: 0 0 auto;
  min-height: 32px;
  padding: 5px 6px;
  gap: 7px;
}
.materi-page.is-detail-open .detail-view .lesson-toc-index {
  width: 19px;
  height: 19px;
  font-size: 9px;
}
.materi-page.is-detail-open .detail-view .lesson-toc-label {
  min-width: 0;
  font-size: 10.5px;
  line-height: 1.3;
  white-space: normal;
  overflow-wrap: anywhere;
}
.materi-page.is-detail-open .detail-view .lesson-toc-practice {
  flex: 0 0 auto;
  margin-top: 5px;
  padding-top: 7px;
}
.materi-page.is-detail-open .detail-view .lesson-video-playlist,
.materi-page.is-detail-open .detail-view .lesson-video-playlist > div {
  width: 100%;
  min-width: 0;
}
.materi-page.is-detail-open .detail-view .lesson-video-playlist iframe {
  display: block;
  width: 100%;
  height: 100%;
  border: 0;
}
@media (max-width: 760px) {
  .materi-page.is-detail-open .detail-view .lesson-layout {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
  }
  .materi-page.is-detail-open .detail-view .lesson-sidebar {
    height: auto;
    max-height: 190px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc {
    height: auto;
    max-height: 190px;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-list {
    flex-direction: row;
    overflow-x: auto;
    overflow-y: hidden;
  }
  .materi-page.is-detail-open .detail-view .lesson-toc-item {
    width: max-content;
    max-width: 230px;
    min-width: 140px;
  }
  .materi-page.is-detail-open .detail-view .lesson-reader {
    height: auto;
    max-height: 100%;
  }
}

/* Paksa rasio player YouTube 16:9; jangan bergantung pada utility aspect-video. */
.materi-page.is-detail-open .detail-view .lesson-video-frame {
  position: relative;
  display: block;
  width: 100%;
  max-width: 100%;
  height: auto !important;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: #020617;
  box-sizing: border-box;
}
.materi-page.is-detail-open .detail-view .lesson-video-frame iframe {
  position: absolute;
  inset: 0;
  display: block;
  width: 100% !important;
  height: 100% !important;
  min-width: 0;
  max-width: 100%;
  border: 0;
}
</style>
