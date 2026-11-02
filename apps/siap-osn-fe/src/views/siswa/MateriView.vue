<script setup>
import { computed, onMounted, ref } from 'vue'
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
const dibukaId = ref(null)
const submateriMenandai = ref(null)

const detail = computed(() => materi.detail)
const submateri = computed(() => detail.value?.submateri ?? [])
const jumlahSubmateriSelesai = computed(() => submateri.value.filter((bagian) => bagian.selesai).length)
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
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal membuka materi', detail: pesanError(err), life: 4000 })
    dibukaId.value = null
  } finally {
    memuatDetail.value = false
  }
}

function tutupDetail() {
  dibukaId.value = null
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
  <div class="materi-page">
    <header class="page-header">
      <div class="header-copy">
        <h1>{{ route.meta.title || 'Materi' }}</h1>
        <p>Materi, latihan soal, dan rekomendasi yang disesuaikan dengan kompetensimu</p>
      </div>
      <UserMenu />
    </header>

    <main class="materi-main">
      <!-- DETAIL MATERI -->
      <section v-if="dibukaId" class="detail-panel">
        <button type="button" class="back-link" @click="tutupDetail">← Kembali ke semua materi</button>
        <div v-if="memuatDetail" class="detail-loading">Memuat materi...</div>
        <template v-else-if="detail">
          <div class="detail-title-row">
            <div>
              <span class="section-kicker">MATERI BELAJAR · {{ namaTingkat(detail.tingkatId) }}</span>
              <h2>{{ detail.judul }}</h2>
              <p>{{ detail.deskripsi }}</p>
            </div>
            <span v-if="detail.wajib" class="pill pill-amber">Materi wajib</span>
          </div>
          <img v-if="detail.gambarUrl" :src="detail.gambarUrl" alt="Ilustrasi materi" class="detail-image" />
          <div class="learning-progress">
            <div class="learning-progress-head">
              <div>
                <strong>Progress belajar</strong>
                <span>{{ jumlahSubmateriSelesai }} dari {{ submateri.length }} submateri selesai</span>
              </div>
              <strong>{{ persentaseDetail }}%</strong>
            </div>
            <div class="learning-progress-track">
              <div class="progress-fill" :style="{ width: persentaseDetail + '%' }"></div>
            </div>
          </div>

          <div class="submateri-layout">
            <aside class="submateri-toc">
              <h3>Daftar isi</h3>
              <nav aria-label="Daftar submateri">
                <a
                  v-for="(bagian, index) in submateri"
                  :key="bagian.id"
                  :href="'#submateri-' + bagian.id"
                  :class="{ 'is-complete': bagian.selesai }"
                >
                  <span class="toc-index">{{ index + 1 }}</span>
                  <span class="toc-title">{{ bagian.judul }}</span>
                  <span class="toc-state">{{ bagian.selesai ? '✓' : '○' }}</span>
                </a>
              </nav>
            </aside>

            <div class="submateri-list">
              <article
                v-for="(bagian, index) in submateri"
                :id="'submateri-' + bagian.id"
                :key="bagian.id"
                class="submateri-card"
              >
                <div class="submateri-meta">
                  <span>SUBMATERI {{ String(index + 1).padStart(2, '0') }}</span>
                  <span :class="bagian.selesai ? 'submateri-status-done' : 'submateri-status-pending'">
                    {{ bagian.selesai ? 'Selesai' : 'Belum selesai' }}
                  </span>
                </div>
                <h3>{{ bagian.judul }}</h3>
                <div
                  v-if="bagian.konten"
                  class="konten-richtext detail-content"
                  v-html="renderKonten(bagian.konten)"
                ></div>
                <p v-else class="submateri-empty">Belum ada isi pada bagian ini.</p>
                <div class="submateri-footer">
                  <button
                    v-if="!bagian.selesai"
                    type="button"
                    class="btn btn-primary"
                    :disabled="submateriMenandai === bagian.id"
                    @click="tandaiSubmateriSelesai(bagian)"
                  >
                    {{ submateriMenandai === bagian.id ? 'Menyimpan...' : 'Tandai Selesai' }}
                  </button>
                  <span v-else class="complete-note">✓ Bagian ini sudah diselesaikan</span>
                </div>
              </article>
            </div>
          </div>
          <div v-if="detail.fileUrl" class="file-link-wrap">
            <a :href="detail.fileUrl" target="_blank" rel="noopener" class="file-link">Buka / unduh file materi ↗</a>
          </div>
          <div class="detail-actions">
            <button
              v-if="detail.latihanTersedia && detail.quizId"
              type="button"
              class="btn btn-outline"
              @click="mulaiLatihan(detail)"
            >
              Mulai Latihan →
            </button>
          </div>
        </template>
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
              @click="pencarian = ''; filterTingkat = 'semua'; filterJenis = 'semua'; filterStatus = 'semua'"
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
              <button type="button" class="btn btn-outline" @click="filterTingkat = 'semua'; filterStatus = 'semua'; filterJenis = 'semua'; pencarian = ''">
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
            <button type="button" class="btn btn-outline" @click="filterTingkat = 'semua'; filterStatus = 'semua'">
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
  background: #f4f7fb;
  color: #14213a;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
.page-header {
  min-height: 56px;
  padding: 10px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: #fff;
  border-bottom: 1px solid #e5eaf1;
}
.header-copy h1 { margin: 0; font-size: 16px; line-height: 1.35; font-weight: 750; }
.header-copy p { margin: 2px 0 0; color: #7c8ba2; font-size: 11px; }
.materi-main { max-width: 1500px; margin: 0 auto; padding: 22px 22px 44px; }
.section-block { margin: 0; }
.section-kicker {
  margin: 0 0 5px;
  color: #e88700;
  font-size: 9px;
  line-height: 1.4;
  font-weight: 800;
  letter-spacing: .035em;
}
.section-block > h2, .detail-title-row h2 {
  margin: 0;
  color: #15223a;
  font-size: 19px;
  line-height: 1.3;
  font-weight: 750;
  letter-spacing: -.025em;
}
.section-desc { margin: 3px 0 14px; color: #7c8ba2; font-size: 10px; line-height: 1.5; }
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 0 5px 14px;
}
.search-box {
  width: min(280px, 100%);
  height: 34px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  border: 1px solid #e3e9f1;
  border-radius: 7px;
  background: #fff;
}
.search-box svg { width: 13px; height: 13px; flex: none; fill: none; stroke: #93a2b7; stroke-width: 1.8; }
.search-box input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: #24334d; font: inherit; font-size: 9px; }
.search-box input::placeholder { color: #a5b1c2; }
.filters { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 7px; }
.filters select {
  max-width: 150px;
  height: 32px;
  padding: 0 24px 0 9px;
  border: 1px solid #e5eaf1;
  border-radius: 7px;
  background: #fff;
  color: #52627a;
  font: inherit;
  font-size: 9px;
  outline: none;
}
.card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.material-card {
  min-width: 0;
  min-height: 126px;
  padding: 12px 13px 10px;
  display: flex;
  flex-direction: column;
  border: 1px solid #e4eaf2;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(20, 40, 75, .025);
}
.material-topline, .material-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pill { display: inline-flex; align-items: center; width: fit-content; padding: 3px 7px; border-radius: 999px; font-size: 8px; line-height: 1.2; font-weight: 700; }
.pill-green { background: #e6fbf2; color: #16865b; }
.pill-yellow { background: #fff2c9; color: #a86a00; }
.pill-amber { background: #fff5dc; color: #a76600; }
.material-type { display: inline-flex; align-items: center; gap: 4px; color: #8b9ab0; font-size: 8px; }
.material-type svg { width: 10px; height: 10px; fill: none; stroke: currentColor; stroke-width: 1.2; }
.material-heading { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
.material-heading h3 { min-width: 0; margin: 0; overflow: hidden; color: #1c2940; font-size: 11px; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
.required-tag { padding: 2px 5px; border-radius: 4px; background: #fff5dc; color: #a76600; font-size: 7px; font-weight: 700; }
.material-description { min-height: 14px; margin: 3px 0 9px; overflow: hidden; color: #8795aa; font-size: 8px; line-height: 1.4; text-overflow: ellipsis; white-space: nowrap; }
.progress-track { width: 100%; height: 4px; margin-top: auto; overflow: hidden; border-radius: 999px; background: #edf1f6; }
.progress-fill { height: 100%; border-radius: inherit; background: #f59e0b; transition: width .25s ease; }
.progress-fill.selesai { background: #10b981; }
.progress-fill.belum { background: #f59e0b; }
.progress-fill.belajar { background: #f59e0b; }
.material-footer { margin-top: 8px; }
.material-footer > span { font-size: 8px; font-weight: 650; }
.status-selesai { color: #10a574; }
.status-belajar { color: #d97706; }
.status-belum { color: #8795aa; }
.text-action { padding: 0; border: 0; background: transparent; color: #27364d; font: inherit; font-size: 8px; font-weight: 700; cursor: pointer; }
.text-action:hover, .back-link:hover { color: #e88700; }
.section-divider { height: 1px; margin: 12px 0 18px; background: #e3e9f1; }
.practice-panel, .recommendation-list, .recommendation-empty, .empty-panel, .detail-panel {
  border: 1px solid #e4eaf2;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(20, 40, 75, .025);
}
.practice-panel { padding: 15px 16px 12px; }
.practice-row {
  min-height: 55px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 76px 70px;
  align-items: center;
  gap: 14px;
  padding: 8px 12px;
  border-bottom: 1px solid #edf1f6;
}
.practice-info { min-width: 0; }
.practice-title { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }
.practice-title strong { color: #1d2b43; font-size: 10px; font-weight: 750; }
.difficulty { padding: 3px 6px; border-radius: 4px; font-size: 7px; font-weight: 750; }
.difficulty-high { background: #fff0d3; color: #b36b00; }
.difficulty-normal { background: #e9f8f1; color: #18865d; }
.practice-info p { margin: 3px 0 0; color: #8b9ab0; font-size: 8px; }
.practice-score { display: flex; flex-direction: column; align-items: center; }
.practice-score strong { color: #17243a; font-size: 13px; font-weight: 800; }
.practice-score span { color: #a0adbd; font-size: 7px; text-align: center; }
.btn { min-height: 29px; padding: 0 13px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: 8px; font: inherit; font-size: 8px; font-weight: 750; white-space: nowrap; cursor: pointer; transition: filter .15s ease, transform .15s ease; }
.btn:hover { filter: brightness(.97); transform: translateY(-1px); }
.btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
.btn-amber { background: #f59e0b; color: #fff; }
.btn-green { background: #35a66f; color: #fff; }
.btn-primary { background: #12315f; color: #fff; }
.btn-outline { border-color: #e0e7f0; background: #fff; color: #42516a; }
.practice-bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 8px 0; }
.practice-bottom > span { color: #9aa7b9; font-size: 8px; }
.practice-empty { padding: 22px 8px; text-align: center; color: #8795aa; font-size: 10px; }
.recommendation-section { padding-bottom: 12px; }
.recommendation-status { min-height: 32px; margin: 0 0 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #9aa7b9; font-size: 8px; }
.recommendation-status strong { color: #64748b; font-weight: 700; }
.recommendation-pill { padding: 5px 8px; border-radius: 6px; background: #e8eef6; color: #52627a; font-size: 8px; }
.recommendation-empty { min-height: 185px; padding: 22px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.empty-icon { width: 38px; height: 38px; margin-bottom: 10px; display: grid; place-items: center; border: 1px solid #dbeafe; border-radius: 11px; background: #f2f7ff; }
.empty-icon svg { width: 19px; height: 19px; fill: none; stroke: #3478ee; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.recommendation-empty h3 { margin: 0; color: #1b2940; font-size: 13px; font-weight: 800; }
.recommendation-empty p { max-width: 430px; margin: 6px 0 13px; color: #8795aa; font-size: 9px; line-height: 1.55; }
.empty-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
.recommendation-list { padding: 8px 16px; }
.recommendation-row { min-height: 66px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 10px 12px; border-bottom: 1px solid #edf1f6; }
.recommendation-row:last-child { border-bottom: 0; }
.recommendation-copy { min-width: 0; }
.recommendation-title { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.recommendation-title strong { color: #1c2940; font-size: 10px; font-weight: 750; }
.priority-label { padding: 3px 7px; border-radius: 4px; font-size: 7px; font-weight: 750; }
.priority-high { background: #ffe8eb; color: #d14359; }
.priority-next { background: #fff2d3; color: #a96b00; }
.recommendation-copy p { margin: 4px 0 0; color: #718198; font-size: 8px; line-height: 1.5; }
.recommendation-progress { display: inline-block; margin-top: 3px; color: #8998ad; font-size: 7px; }
.empty-panel { padding: 24px; text-align: center; }
.empty-panel h3 { margin: 0; font-size: 13px; }
.empty-panel p { margin: 6px 0 12px; color: #8795aa; font-size: 10px; }
.detail-panel { padding: 20px; }
.back-link { padding: 0; border: 0; background: transparent; color: #3268c8; font: inherit; font-size: 10px; font-weight: 700; cursor: pointer; }
.detail-title-row { margin-top: 16px; display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.detail-title-row h2 { margin-top: 5px; }
.detail-title-row p { margin: 5px 0 0; color: #8795aa; font-size: 10px; }
.detail-image { max-width: 100%; max-height: 360px; margin-top: 18px; border-radius: 10px; object-fit: contain; }
.detail-content { margin-top: 14px; color: #334155; font-size: 13px; line-height: 1.8; }
.learning-progress { margin-top: 18px; padding: 13px 15px; border: 1px solid #e4eaf2; border-radius: 10px; background: #f8fafc; }
.learning-progress-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.learning-progress-head > div { display: flex; flex-direction: column; gap: 3px; }
.learning-progress-head strong { color: #24334d; font-size: 10px; font-weight: 800; }
.learning-progress-head span { color: #8795aa; font-size: 8px; }
.learning-progress-head > strong { color: #d97706; font-size: 13px; }
.learning-progress-track { height: 6px; margin-top: 10px; overflow: hidden; border-radius: 999px; background: #e8edf4; }
.learning-progress-track .progress-fill { background: #10b981; }
.submateri-layout { display: grid; grid-template-columns: 220px minmax(0, 1fr); align-items: start; gap: 16px; margin-top: 16px; }
.submateri-toc { position: sticky; top: 14px; padding: 13px; border: 1px solid #e4eaf2; border-radius: 10px; background: #fbfcfe; }
.submateri-toc h3 { margin: 0 0 9px; color: #24334d; font-size: 10px; font-weight: 800; }
.submateri-toc nav { display: grid; gap: 4px; }
.submateri-toc a { min-width: 0; display: grid; grid-template-columns: 20px minmax(0, 1fr) 14px; align-items: center; gap: 6px; padding: 7px 6px; border-radius: 6px; color: #64748b; font-size: 8px; line-height: 1.4; text-decoration: none; }
.submateri-toc a:hover { background: #eef3f9; color: #24334d; }
.submateri-toc a.is-complete { color: #13865e; }
.toc-index { color: #a0adbd; font-size: 8px; font-weight: 750; text-align: center; }
.toc-title { overflow-wrap: anywhere; }
.toc-state { font-weight: 800; text-align: center; }
.submateri-list { min-width: 0; display: grid; gap: 13px; }
.submateri-card { min-width: 0; padding: 17px 18px; border: 1px solid #e4eaf2; border-radius: 10px; background: #fff; scroll-margin-top: 18px; }
.submateri-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.submateri-meta > span:first-child { color: #a0adbd; font-size: 7px; font-weight: 800; letter-spacing: .05em; }
.submateri-status-done, .submateri-status-pending { padding: 3px 7px; border-radius: 999px; font-size: 7px; font-weight: 750; }
.submateri-status-done { background: #e6fbf2; color: #16865b; }
.submateri-status-pending { background: #fff5dc; color: #a76600; }
.submateri-card h3 { margin: 9px 0 0; color: #1c2940; font-size: 14px; font-weight: 800; }
.submateri-card .detail-content { margin-top: 8px; }
.submateri-empty { margin: 10px 0 0; color: #8795aa; font-size: 9px; }
.submateri-footer { display: flex; align-items: center; justify-content: flex-end; margin-top: 14px; padding-top: 11px; border-top: 1px solid #edf1f6; }
.submateri-footer .complete-note { font-size: 9px; }
.file-link-wrap { margin-top: 16px; }
.file-link { color: #2563eb; font-size: 11px; font-weight: 700; }
.detail-actions { margin-top: 22px; display: flex; flex-wrap: wrap; align-items: center; gap: 9px; }
.complete-note { color: #14956a; font-size: 10px; font-weight: 700; }
.detail-loading { padding: 35px 0; color: #8795aa; font-size: 11px; text-align: center; }
.loading-card { gap: 9px; }
.skeleton { display: block; border-radius: 5px; background: linear-gradient(90deg, #e8edf4 25%, #f4f6f9 45%, #e8edf4 65%); background-size: 220% 100%; animation: shimmer 1.35s ease-in-out infinite; }
@keyframes shimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
.sk-pill { width: 65px; height: 16px; border-radius: 999px; }
.sk-title { width: 45%; height: 13px; margin-top: 3px; }
.sk-copy { width: 80%; height: 9px; }
.sk-progress { width: 100%; height: 4px; margin-top: auto; }
.sk-footer { width: 100%; height: 9px; }
.practice-loading { display: grid; gap: 10px; }
.practice-skeleton { height: 38px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

@media (max-width: 900px) {
  .materi-main { padding: 18px 16px 32px; }
  .toolbar { align-items: stretch; flex-direction: column; margin-inline: 0; }
  .search-box { width: 100%; }
  .filters { justify-content: flex-start; }
  .card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
  .submateri-layout { grid-template-columns: 1fr; }
  .submateri-toc { position: static; }
  .submateri-toc nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
  .page-header { padding: 10px 14px; }
  .header-copy p { max-width: 235px; }
  .materi-main { padding: 16px 12px 28px; }
  .card-grid { grid-template-columns: 1fr; }
  .practice-panel { padding: 10px; }
  .practice-row { grid-template-columns: minmax(0, 1fr) 52px; gap: 8px; padding-inline: 5px; }
  .practice-row > .btn { grid-column: 1 / -1; justify-self: end; }
  .recommendation-row { align-items: flex-start; flex-direction: column; gap: 8px; }
  .recommendation-row > .btn { align-self: flex-end; }
  .recommendation-status { align-items: flex-start; flex-direction: column; padding: 6px 0; }
}
</style>
