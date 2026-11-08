<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'
import { usePretestStore } from '@/stores/pretest.js'
import { useProgressStore } from '@/stores/progress.js'
import { simulasiService } from '@/services/simulasi.js'
import { riwayatService } from '@/services/riwayat.js'
import { pesanError } from '@/lib/errors.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const pretest = usePretestStore()
const progress = useProgressStore()

/* Tingkat dan ruang simulasi */
const levels = computed(() => pretest.tingkatList)
const ruangPerTingkat = ref({})
const memuatRuang = ref(false)

function bolehPilih(level) {
  return Boolean(level?.terbuka)
}

function simulasiUntuk(level) {
  const daftar = ruangPerTingkat.value[level?.id]?.daftar ?? []
  return daftar.find((item) => item.aktif) ?? daftar[0] ?? null
}

function syaratUntuk(level) {
  return ruangPerTingkat.value[level?.id]?.syarat ?? null
}

function statusLevel(level) {
  if (!level.terbuka) return 'Terkunci'
  if (level.tahap === 'LULUS') return 'Sudah Dilalui'
  if (level.tahap === 'SIMULASI_BERJALAN') return 'Sedang Berjalan'
  if (level.tahap === 'PUTARAN_HABIS') return 'Percobaan Habis'
  return syaratUntuk(level)?.terpenuhi ? 'Tersedia' : 'Belum Memenuhi Syarat'
}

function kelasStatusLevel(level) {
  if (!level.terbuka || level.tahap === 'PUTARAN_HABIS') return 'status-muted'
  if (level.tahap === 'LULUS') return 'status-success'
  if (level.tahap === 'SIMULASI_BERJALAN') return 'status-warning'
  return syaratUntuk(level)?.terpenuhi ? 'status-success' : 'status-muted'
}

function alasanMulaiNonaktif(item, level) {
  if (!item) return 'Belum ada simulasi yang tersedia untuk tingkat ini.'
  if (!bolehPilih(level)) return 'Tingkat ini belum dapat diakses.'
  if (!item.aktif) return 'Simulasi ini sedang tidak aktif.'

  // Percobaan yang sedang berjalan harus tetap bisa dilanjutkan walaupun
  // kuota tersisa sudah nol atau syarat putaran sudah berubah.
  if (level.tahap === 'SIMULASI_BERJALAN') return ''

  const syarat = syaratUntuk(level)
  if (!syarat) return 'Syarat simulasi belum dapat diperiksa. Coba muat ulang halaman.'
  if (!syarat.terpenuhi) {
    if (syarat.alasan === 'belum_pretest') return 'Selesaikan pre-test terlebih dahulu.'
    return 'Selesaikan materi dan latihan wajib sebelum memulai simulasi.'
  }
  if (item.sisaKuota === 0) return 'Kuota simulasi untuk putaran ini sudah habis.'
  return ''
}

function bisaMulai(item, level) {
  return alasanMulaiNonaktif(item, level) === ''
}

async function muatRuangSemuaTingkat() {
  memuatRuang.value = true
  try {
    const dapatDiakses = levels.value.filter((level) => level.terbuka)
    const hasil = await Promise.all(
      dapatDiakses.map(async (level) => {
        const [daftarResult, syaratResult] = await Promise.allSettled([
          simulasiService.daftar({ tingkatId: level.id }),
          simulasiService.syarat({ tingkatId: level.id }),
        ])
        return [
          level.id,
          {
            daftar: daftarResult.status === 'fulfilled' ? daftarResult.value : [],
            syarat: syaratResult.status === 'fulfilled' ? syaratResult.value : null,
          },
        ]
      }),
    )
    ruangPerTingkat.value = Object.fromEntries(hasil)
  } finally {
    memuatRuang.value = false
  }
}

/* Navigasi dari kartu tingkat */
function lihatRiwayat() {
  router.push({ name: 'siswa.riwayat' })
}

function klikTingkat(level) {
  if (level.tahap === 'LULUS') {
    lihatRiwayat()
    return
  }

  const item = simulasiUntuk(level)
  const alasan = alasanMulaiNonaktif(item, level)
  if (alasan) {
    toast.add({
      severity: 'warn',
      summary: 'Simulasi belum dapat dimulai',
      detail: alasan,
      life: 4000,
    })
    return
  }

  bukaModal(item, level)
}

/* Modal aturan simulasi */
const modalTerbuka = ref(false)
const simulasiDipilih = ref(null)
const levelDipilihId = ref(null)
const setuju = ref(false)
const modalEl = ref(null)

const levelDipilih = computed(
  () => levels.value.find((level) => level.id === levelDipilihId.value) ?? null,
)
const simulasiBerjalan = computed(() => levelDipilih.value?.tahap === 'SIMULASI_BERJALAN')
const aturan = computed(() => {
  const menit = simulasiDipilih.value?.durasiMenit ?? 0
  return [
    {
      judul: 'Timer Berjalan Otomatis',
      isi: 'Timer mulai berjalan langsung setelah simulasi dimulai dan tidak dapat dijeda atau dihentikan sementara.',
    },
    {
      judul: 'Penyimpanan Jawaban Otomatis (Autosave)',
      isi: 'Setiap opsi yang kamu klik akan otomatis tersimpan ke server evaluasi secara real-time.',
    },
    {
      judul: 'Navigasi Bebas Antarsoal',
      isi: 'Kamu bebas berpindah antarsoal kapan saja melalui panel nomor soal di sisi kanan ruang ujian.',
    },
    {
      judul: 'Pengumpulan Otomatis oleh Sistem',
      isi: 'Simulasi akan dikumpulkan secara otomatis oleh sistem tepat saat waktu ' + menit + ' menit habis.',
    },
    {
      judul: 'Kestabilan Koneksi Jaringan',
      isi: 'Pastikan koneksi internet tetap aktif dan stabil selama pengerjaan untuk menghindari keterlambatan sinkronisasi data.',
    },
  ]
})

function bukaModal(item, level) {
  simulasiDipilih.value = item
  levelDipilihId.value = level.id
  setuju.value = false
  modalTerbuka.value = true
}

function tutupModal() {
  modalTerbuka.value = false
}

function mulaiUjian() {
  if (!setuju.value || !simulasiDipilih.value) return
  const item = simulasiDipilih.value
  modalTerbuka.value = false
  router.push({ name: 'siswa.ujian', params: { simulasiId: item.id } })
}

watch(modalTerbuka, (terbuka) => {
  document.body.style.overflow = terbuka ? 'hidden' : ''
  if (terbuka) nextTick(() => modalEl.value?.focus())
})

function onKeydown(event) {
  if (event.key === 'Escape' && modalTerbuka.value) tutupModal()
}

/* Hasil simulasi terakhir dan kisi pembahasan */
const hasilTerakhir = computed(() => progress.data.hasilTerakhir)
const hasilDetail = ref(null)
const hasilSebelumnya = ref(null)
const memuatHasil = ref(false)
const galatHasil = ref(false)

const skor = computed(() => {
  const nilai = hasilDetail.value?.nilai ?? hasilTerakhir.value?.nilai
  return nilai == null ? 0 : Math.max(0, Math.min(100, Number(nilai)))
})

const R = 36
const keliling = 2 * Math.PI * R
const offset = computed(() => keliling * (1 - skor.value / 100))

const kisiJawaban = computed(() => hasilDetail.value?.jawaban ?? [])
const jumlahBenar = computed(
  () => hasilDetail.value?.jumlahBenar ?? kisiJawaban.value.filter((item) => item.benar).length,
)
const jumlahSalah = computed(
  () => hasilDetail.value?.jumlahSalah ?? kisiJawaban.value.filter((item) => !item.benar).length,
)
const totalSoalHasil = computed(
  () => kisiJawaban.value.length || jumlahBenar.value + jumlahSalah.value,
)

const durasiMenit = computed(() => {
  const mulai = Date.parse(hasilDetail.value?.mulaiPada ?? '')
  const selesai = Date.parse(
    hasilDetail.value?.disubmitPada ?? hasilDetail.value?.selesaiPada ?? '',
  )
  if (Number.isFinite(mulai) && Number.isFinite(selesai) && selesai >= mulai) {
    return Math.max(1, Math.round((selesai - mulai) / 60000))
  }

  const tingkatId = hasilTerakhir.value?.tingkatId
  const fallback = ruangPerTingkat.value[tingkatId]?.daftar?.[0]?.durasiMenit
  return Number.isFinite(Number(fallback)) && Number(fallback) > 0 ? Number(fallback) : null
})

const perubahanNilai = computed(() => {
  const sekarang = hasilDetail.value?.nilai ?? hasilTerakhir.value?.nilai
  const sebelumnya = hasilSebelumnya.value?.nilai
  if (sekarang == null || sebelumnya == null) return null
  return Math.round(Number(sekarang) - Number(sebelumnya))
})

function labelPerubahan() {
  if (perubahanNilai.value == null) return '—'
  if (perubahanNilai.value > 0) return '+' + perubahanNilai.value
  if (perubahanNilai.value < 0) return '−' + Math.abs(perubahanNilai.value)
  return '0'
}

function formatTanggal(iso) {
  if (!iso) return '—'
  const tanggal = new Date(iso)
  return Number.isNaN(tanggal.getTime())
    ? '—'
    : tanggal.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
}

const judulHasilTerakhir = computed(() => {
  const judul = hasilTerakhir.value?.judul ?? 'Simulasi Seleksi'
  return judul.replace('Simulasi —', 'Simulasi Seleksi —')
})

async function muatDetailHasilTerakhir() {
  const ringkasan = hasilTerakhir.value
  if (!ringkasan?.id) return

  memuatHasil.value = true
  galatHasil.value = false

  const tugas = [
    simulasiService
      .lihat({ id: ringkasan.id })
      .then((paket) => {
        if (paket.jenis === 'hasil') hasilDetail.value = paket.hasil
      }),
  ]

  if (ringkasan.tingkatId) {
    tugas.push(
      riwayatService
        .daftar({
          jenis: 'simulasi',
          tingkatId: ringkasan.tingkatId,
          perPage: 20,
        })
        .then((riwayat) => {
          const daftar = riwayat.items
            .filter((item) => item.nilai != null)
            .sort((a, b) => new Date(b.tanggal).getTime() - new Date(a.tanggal).getTime())
          const indeksTerakhir = daftar.findIndex(
            (item) => Number(item.referensiId) === Number(ringkasan.id),
          )
          hasilSebelumnya.value =
            indeksTerakhir >= 0
              ? daftar[indeksTerakhir + 1] ?? null
              : daftar.find((item) => Number(item.referensiId) !== Number(ringkasan.id)) ?? null
        }),
    )
  }

  const hasilTugas = await Promise.allSettled(tugas)
  galatHasil.value = hasilTugas[0]?.status === 'rejected'
  memuatHasil.value = false
}

/* Navigasi hasil dan pembahasan */
function lihatHasil(hasilId) {
  if (!hasilId) return lihatRiwayat()
  router.push({ name: 'siswa.simulasi.hasil', params: { hasilId } })
}

function lihatPembahasan(hasilId) {
  if (!hasilId) return lihatRiwayat()
  router.push({ name: 'siswa.simulasi.review', params: { hasilId } })
}

onMounted(async () => {
  await progress.fetchDashboard().catch(() => {})
  try {
    await pretest.muatTingkat()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Gagal memuat daftar tingkat',
      detail: pesanError(error),
      life: 4000,
    })
  }

  await muatRuangSemuaTingkat()
  await muatDetailHasilTerakhir()
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="halaman">
    <header class="topbar">
      <div class="header-copy">
        <h1>{{ route.meta.title || 'Simulasi Seleksi' }}</h1>
        <p>Rasakan kondisi ujian sesungguhnya, sesuai standar TOKI</p>
      </div>
      <UserMenu />
    </header>

    <main class="konten">
      <section class="section-block">
        <span class="eyebrow">01 · PILIH TINGKAT</span>
        <h2>Pilih Tingkat Simulasi</h2>
        <p class="sub">Tekan Mulai Simulasi untuk membuka halaman ujian.</p>

        <div v-if="memuatRuang && !levels.length" class="empty-card">
          Memuat tingkat simulasi...
        </div>

        <div v-else-if="levels.length" class="grid-level">
          <article
            v-for="level in levels"
            :key="level.id"
            class="level-card"
            :class="{
              'is-current': level.tahap === 'SIMULASI_BERJALAN',
              'is-passed': level.tahap === 'LULUS',
              'is-locked': !bolehPilih(level),
            }"
          >
            <div class="level-card-top">
              <span class="status-badge" :class="kelasStatusLevel(level)">
                {{ statusLevel(level) }}
              </span>
            </div>

            <h3>{{ level.nama }}</h3>
            <p class="level-description">
              {{ level.deskripsi || 'Persiapkan dirimu untuk mengikuti seleksi OSN tingkat ' + level.nama + '.' }}
            </p>

            <p
              v-if="alasanMulaiNonaktif(simulasiUntuk(level), level) && level.tahap !== 'LULUS'"
              class="level-notice"
            >
              {{ alasanMulaiNonaktif(simulasiUntuk(level), level) }}
            </p>

            <div class="level-meta">
              <span>
                <svg viewBox="0 0 20 20" aria-hidden="true">
                  <path d="M5 2.8h7l3 3v11.4H5z" />
                  <path d="M12 2.8v3h3M7.5 9h5M7.5 12h5" />
                </svg>
                {{ simulasiUntuk(level)?.jumlahSoal ?? '—' }} soal
              </span>
              <span>
                <svg viewBox="0 0 20 20" aria-hidden="true">
                  <circle cx="10" cy="10.5" r="6.5" />
                  <path d="M10 7v3.8l2.5 1.5M7.5 2.5h5" />
                </svg>
                {{ simulasiUntuk(level)?.durasiMenit ?? '—' }} menit
              </span>
            </div>

            <button
              type="button"
              class="btn-level"
              :class="level.tahap === 'LULUS' ? 'btn-history' : 'btn-start'"
              :disabled="
                level.tahap !== 'LULUS' &&
                (!bolehPilih(level) || !bisaMulai(simulasiUntuk(level), level))
              "
              @click="klikTingkat(level)"
            >
              {{
                level.tahap === 'LULUS'
                  ? 'Lihat Riwayat'
                  : level.tahap === 'SIMULASI_BERJALAN'
                    ? 'Lanjutkan Simulasi →'
                    : !bolehPilih(level)
                      ? 'Terkunci'
                      : bisaMulai(simulasiUntuk(level), level)
                        ? 'Mulai Simulasi →'
                        : 'Belum Tersedia'
              }}
            </button>
          </article>
        </div>

        <div v-else class="empty-card">
          <strong>Belum ada tingkat simulasi</strong>
          <p>Daftar tingkat belum tersedia. Silakan muat ulang halaman.</p>
        </div>
      </section>

      <div class="section-divider"></div>

      <section class="section-block">
        <span class="eyebrow">02 · HASIL TERAKHIR</span>
        <h2>Hasil Simulasi Terakhir</h2>
        <p class="sub">Ringkasan skor dari simulasi yang baru kamu selesaikan.</p>

        <article v-if="hasilTerakhir" class="result-card">
          <div class="result-heading">
            <div class="score-ring">
              <svg viewBox="0 0 88 88" aria-hidden="true">
                <circle cx="44" cy="44" :r="R" fill="none" stroke="#edf1f5" stroke-width="7" />
                <circle
                  cx="44"
                  cy="44"
                  :r="R"
                  fill="none"
                  stroke="#f59e0b"
                  stroke-width="7"
                  stroke-linecap="round"
                  :stroke-dasharray="keliling"
                  :stroke-dashoffset="offset"
                  transform="rotate(-90 44 44)"
                />
              </svg>
              <div class="score-ring-label">
                <strong>{{ Math.round(skor) }}%</strong>
                <small>Skor</small>
              </div>
            </div>

            <div class="result-title">
              <h3>{{ judulHasilTerakhir }}</h3>
              <p>
                {{ formatTanggal(hasilTerakhir.tanggal) }}
                <span v-if="hasilDetail?.jumlahBenar != null">
                  · {{ jumlahBenar }} dari {{ totalSoalHasil }} benar
                </span>
                <span v-if="durasiMenit != null"> · Durasi {{ durasiMenit }} menit</span>
              </p>
            </div>
          </div>

          <div class="result-stats">
            <div class="result-stat">
              <strong>{{ hasilDetail ? jumlahBenar : '—' }}</strong>
              <span>Benar</span>
            </div>
            <div class="result-stat">
              <strong>{{ hasilDetail ? jumlahSalah : '—' }}</strong>
              <span>Salah</span>
            </div>
            <div class="result-stat">
              <strong>{{ durasiMenit == null ? '—' : durasiMenit + 'm' }}</strong>
              <span>Durasi</span>
            </div>
            <div class="result-stat">
              <strong :class="{
                'delta-positive': perubahanNilai > 0,
                'delta-negative': perubahanNilai < 0,
              }">
                {{ labelPerubahan() }}
              </strong>
              <span>Dari sebelumnya</span>
            </div>
          </div>

          <p v-if="memuatHasil" class="result-footnote">Memuat rincian hasil simulasi...</p>
          <p v-else-if="galatHasil" class="result-footnote">
            Rincian hasil belum dapat dimuat. Kamu tetap bisa membuka hasil melalui riwayat.
            <button type="button" class="inline-link" @click="lihatHasil(hasilTerakhir.id)">
              Lihat hasil
            </button>
          </p>
        </article>

        <div v-else class="empty-card result-empty">
          <strong>Belum ada hasil simulasi</strong>
          <p>Selesaikan satu simulasi untuk melihat ringkasan nilai dan perkembanganmu di sini.</p>
          <button type="button" class="btn-level btn-start empty-action" @click="lihatRiwayat">
            Lihat Riwayat →
          </button>
        </div>
      </section>

      <div class="section-divider section-divider-large"></div>

      <section class="section-block">
        <span class="eyebrow">03 · PEMBAHASAN</span>
        <h2>Pembahasan Soal</h2>
        <p class="sub">Jawabanmu dibandingkan dengan jawaban benar, lengkap dengan penjelasannya.</p>

        <article v-if="hasilTerakhir" class="discussion-card">
          <div class="discussion-copy">
            <h3>{{ totalSoalHasil || hasilDetail?.jawaban?.length || '—' }} soal siap dibahas</h3>
            <p>
              Dari {{ judulHasilTerakhir }}
              <span v-if="hasilTerakhir.tanggal"> · {{ formatTanggal(hasilTerakhir.tanggal) }}</span>
            </p>

            <div v-if="kisiJawaban.length" class="answer-grid" aria-label="Status jawaban setiap soal">
              <span
                v-for="jawaban in kisiJawaban"
                :key="jawaban.soalId"
                class="answer-cell"
                :class="jawaban.benar ? 'answer-correct' : 'answer-wrong'"
                :title="'Soal ' + jawaban.urutan + (jawaban.benar ? ' benar' : ' salah')"
              ></span>
            </div>
            <p v-else-if="memuatHasil" class="discussion-loading">Memuat status jawaban...</p>
            <p v-else class="discussion-loading">Kisi jawaban belum tersedia.</p>

            <div v-if="kisiJawaban.length" class="answer-legend">
              <span><i class="legend-dot correct-dot"></i>{{ jumlahBenar }} benar</span>
              <span><i class="legend-dot wrong-dot"></i>{{ jumlahSalah }} salah</span>
            </div>
          </div>

          <button
            type="button"
            class="btn-discussion"
            :disabled="!hasilTerakhir.id"
            @click="lihatPembahasan(hasilTerakhir.id)"
          >
            Lihat Pembahasan Soal →
          </button>
        </article>

        <div v-else class="empty-card">
          <strong>Pembahasan belum tersedia</strong>
          <p>Pembahasan soal akan muncul setelah kamu menyelesaikan simulasi pertama.</p>
        </div>
      </section>
    </main>

    <Teleport to="body">
      <Transition name="fade-modal">
        <div v-if="modalTerbuka && simulasiDipilih" class="overlay" @click.self="tutupModal">
          <div
            ref="modalEl"
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judul-modal"
            tabindex="-1"
          >
            <div class="modal-header">
              <h3 id="judul-modal" class="modal-title">
                <span class="modal-title-dot"></span>
                Detail Simulasi
              </h3>
              <span class="modal-status">
                {{ simulasiBerjalan ? 'Simulasi Sedang Berjalan' : 'Tersedia untuk Dikerjakan' }}
              </span>
            </div>

            <div class="modal-info-grid">
              <div class="modal-info-card">
                <span class="modal-info-label">TINGKAT SELEKSI</span>
                <strong class="modal-info-value">{{ levelDipilih?.nama ?? '—' }}</strong>
                <span class="modal-info-tag">OSN-P {{ new Date().getFullYear() }}</span>
              </div>

              <div class="modal-info-card">
                <span class="modal-info-label">JUMLAH SOAL</span>
                <strong class="modal-info-value">
                  {{ simulasiDipilih.jumlahSoal }} <span>Soal</span>
                </strong>
                <span class="modal-info-sub">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                  </svg>
                  Bank Soal Acak
                </span>
              </div>

              <div class="modal-info-card">
                <span class="modal-info-label">DURASI WAKTU</span>
                <strong class="modal-info-value">
                  {{ simulasiDipilih.durasiMenit }} <span>Menit</span>
                </strong>
                <span class="modal-info-sub">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="13" r="8" />
                    <path d="M12 9v4l2 2M9 2h6" />
                  </svg>
                  Countdown Statis
                </span>
              </div>

              <div class="modal-info-card">
                <span class="modal-info-label">TIPE SOAL</span>
                <strong class="modal-info-value">Pilihan Ganda</strong>
                <span class="modal-info-sub">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8 12 2.5 2.5L16 9" />
                  </svg>
                  5 Opsi (A–E)
                </span>
              </div>
            </div>

            <hr class="modal-divider" />

            <h4 class="rules-heading">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z" />
                <path d="M9 12l2 2 4-4" />
              </svg>
              Aturan dan Ketentuan Simulasi
            </h4>

            <ol class="rules-list">
              <li v-for="(item, index) in aturan" :key="item.judul">
                <span class="rule-number">{{ index + 1 }}</span>
                <div>
                  <strong>{{ item.judul }}</strong>
                  <p>{{ item.isi }}</p>
                </div>
              </li>
            </ol>

            <label class="agreement">
              <input v-model="setuju" type="checkbox" />
              <span>Saya telah membaca dan memahami aturan simulasi.</span>
            </label>

            <div class="modal-footer">
              <button type="button" class="modal-button button-cancel" @click="tutupModal">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
                Batal
              </button>
              <button
                type="button"
                class="modal-button button-confirm"
                :disabled="!setuju"
                @click="mulaiUjian"
              >
                {{ simulasiBerjalan ? 'Lanjutkan Simulasi' : 'Mulai Simulasi' }}
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.halaman {
  min-height: 100%;
  background: #f5f8fc;
  color: #111f38;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.topbar {
  min-height: 70px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 14px 24px;
  background: #fff;
  border-bottom: 1px solid #e4eaf2;
}

.header-copy h1 {
  margin: 0;
  color: #111f38;
  font-size: 17px;
  line-height: 1.3;
  font-weight: 750;
}

.header-copy p {
  margin: 3px 0 0;
  color: #71819a;
  font-size: 12px;
  line-height: 1.45;
}

.konten {
  --ink: #111f38;
  --muted: #71819a;
  --line: #e4eaf2;
  --orange: #f59e0b;
  --green: #159b66;
  width: 100%;
  max-width: 1500px;
  margin: 0 auto;
  padding: 27px 24px 52px;
  color: var(--ink);
}

.konten,
.konten *,
.konten *::before,
.konten *::after {
  box-sizing: border-box;
}

.section-block {
  min-width: 0;
}

.eyebrow {
  display: block;
  margin-bottom: 5px;
  color: #d97706;
  font-size: 10px;
  line-height: 1.4;
  font-weight: 800;
  letter-spacing: 0.055em;
  text-transform: uppercase;
}

.section-block > h2 {
  margin: 0;
  color: #111f38;
  font-size: 21px;
  line-height: 1.3;
  font-weight: 750;
  letter-spacing: -0.025em;
}

.sub {
  margin: 3px 0 13px;
  color: var(--muted);
  font-size: 11.5px;
  line-height: 1.5;
}

.section-divider {
  height: 1px;
  margin: 16px 0 23px;
  background: #e3eaf2;
}

.section-divider-large {
  margin-top: 47px;
  margin-bottom: 24px;
}

.grid-level {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  align-items: stretch;
}

.level-card {
  min-width: 0;
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  padding: 19px 20px 17px;
  border: 1px solid #e2e9f2;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 35 65 / 4%);
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.level-card.is-current {
  border: 1.5px solid #f5a000;
  box-shadow: 0 2px 7px rgb(245 158 11 / 8%);
}

.level-card.is-passed {
  border-color: #e2e9f2;
}

.level-card.is-locked {
  background: #fbfcfe;
}

.level-card-top {
  min-height: 23px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 10px;
  line-height: 1.2;
  font-weight: 650;
}

.status-success {
  background: #e8faf2;
  color: #087b53;
}

.status-warning {
  background: #fff4d9;
  color: #a45a00;
}

.status-muted {
  background: #eef2f7;
  color: #718096;
}

.level-card h3 {
  margin: 10px 0 7px;
  color: #17243b;
  font-size: 15px;
  line-height: 1.3;
  font-weight: 750;
}

.level-description {
  max-width: 390px;
  min-height: 34px;
  margin: 0;
  color: #77869c;
  font-size: 10.5px;
  line-height: 1.55;
}

.level-notice {
  margin: 7px 0 0;
  color: #a45a00;
  font-size: 10px;
  line-height: 1.45;
}

.level-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  margin: auto 0 15px;
  padding-top: 11px;
  color: #53647e;
  font-size: 10.5px;
}

.level-meta span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.level-meta svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: #8291a6;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.btn-level {
  min-height: 34px;
  width: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 14px;
  border: 0;
  border-radius: 9px;
  font: inherit;
  font-size: 10.5px;
  line-height: 1.2;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.15s ease, opacity 0.15s ease;
}

.btn-level:hover:not(:disabled) {
  transform: translateY(-1px);
}

.btn-level:focus-visible,
.btn-discussion:focus-visible,
.inline-link:focus-visible {
  outline: 2px solid #1d4ed8;
  outline-offset: 2px;
}

.btn-level:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-start {
  background: #f59e0b;
  color: #fff;
}

.btn-start:hover:not(:disabled) {
  background: #e99100;
}

.btn-history {
  background: #e7f7f1;
  color: #087b53;
}

.btn-history:hover:not(:disabled) {
  background: #d9f1e8;
}

.empty-card {
  padding: 25px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.6;
  text-align: center;
}

.empty-card strong {
  display: block;
  color: #263650;
  font-size: 14px;
}

.empty-card p {
  margin: 5px 0 0;
}

.result-card {
  padding: 20px 22px 18px;
  border: 1px solid #e3eaf2;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 35 65 / 4%);
}

.result-heading {
  display: flex;
  align-items: center;
  gap: 17px;
  min-width: 0;
  margin-bottom: 19px;
}

.score-ring {
  position: relative;
  width: 70px;
  height: 70px;
  flex: 0 0 70px;
}

.score-ring svg {
  display: block;
  width: 100%;
  height: 100%;
}

.score-ring-label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.score-ring-label strong {
  color: #17243b;
  font-size: 14px;
  line-height: 1.1;
  font-weight: 800;
}

.score-ring-label small {
  margin-top: 2px;
  color: #8a98aa;
  font-size: 8px;
}

.result-title {
  min-width: 0;
}

.result-title h3 {
  margin: 0;
  color: #17243b;
  font-size: 13px;
  line-height: 1.4;
  font-weight: 750;
}

.result-title p {
  margin: 4px 0 0;
  color: #7a8aa1;
  font-size: 9.5px;
  line-height: 1.5;
}

.result-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.result-stat {
  min-height: 68px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 1px solid #edf1f6;
  border-radius: 11px;
  background: #f8fafc;
}

.result-stat strong {
  color: #17243b;
  font-size: 17px;
  line-height: 1.15;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.result-stat span {
  color: #8291a6;
  font-size: 9px;
}

.result-stat .delta-positive {
  color: #159b66;
}

.result-stat .delta-negative {
  color: #dc3e55;
}

.result-footnote {
  margin: 11px 0 0;
  color: #8291a6;
  font-size: 10px;
  line-height: 1.5;
}

.inline-link {
  padding: 0;
  border: 0;
  background: none;
  color: #1d4ed8;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.result-empty {
  text-align: left;
}

.empty-action {
  width: auto;
  min-width: 140px;
  margin-top: 14px;
}

.discussion-card {
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 19px 20px;
  border: 1px solid #e3eaf2;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 35 65 / 4%);
}

.discussion-copy {
  min-width: 0;
}

.discussion-copy h3 {
  margin: 0;
  color: #17243b;
  font-size: 12px;
  line-height: 1.4;
  font-weight: 750;
}

.discussion-copy > p {
  margin: 3px 0 0;
  color: #7a8aa1;
  font-size: 10px;
  line-height: 1.5;
}

.answer-grid {
  width: fit-content;
  max-width: 100%;
  display: grid;
  grid-template-columns: repeat(16, 14px);
  gap: 4px;
  margin-top: 11px;
}

.answer-cell {
  width: 14px;
  height: 14px;
  border: 1px solid;
  border-radius: 4px;
}

.answer-correct {
  border-color: #bcebd2;
  background: #e5f8ee;
}

.answer-wrong {
  border-color: #f7c7d0;
  background: #fff0f2;
}

.answer-legend {
  display: flex;
  align-items: center;
  gap: 13px;
  margin-top: 8px;
  color: #7b8aa0;
  font-size: 9px;
}

.answer-legend span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.legend-dot {
  width: 7px;
  height: 7px;
  display: inline-block;
  border-radius: 50%;
}

.correct-dot {
  background: #16a365;
}

.wrong-dot {
  background: #e43e5c;
}

.discussion-loading {
  margin-top: 10px !important;
  color: #8b98aa !important;
}

.btn-discussion {
  min-height: 36px;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 16px;
  border: 0;
  border-radius: 9px;
  background: #f59e0b;
  color: #fff;
  font: inherit;
  font-size: 10px;
  line-height: 1.2;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, opacity 0.15s ease;
}

.btn-discussion:hover:not(:disabled) {
  background: #e99100;
}

.btn-discussion:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* Modal konfirmasi */
.overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  overflow-y: auto;
  background: rgb(8 25 49 / 48%);
  backdrop-filter: blur(2px);
}

.overlay,
.overlay *,
.overlay *::before,
.overlay *::after {
  box-sizing: border-box;
}

.modal {
  width: 100%;
  max-width: 1016px;
  max-height: calc(100vh - 48px);
  margin: auto;
  padding: 42px 60px 46px;
  overflow-y: auto;
  border-radius: 15px;
  background: #fff;
  color: #10213d;
  box-shadow: 0 24px 65px rgb(7 22 43 / 24%);
  outline: none;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 25px;
}

.modal-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  color: #10213d;
  font-size: 13px;
  font-weight: 750;
}

.modal-title-dot {
  width: 9px;
  height: 9px;
  display: inline-block;
  border-radius: 50%;
  background: #f59e0b;
}

.modal-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: 999px;
  background: #ffe0b6;
  color: #663800;
  font-size: 11px;
  font-weight: 650;
}

.modal-status::before {
  content: "";
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.modal-info-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 13px;
}

.modal-info-card {
  min-width: 0;
  min-height: 106px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 7px;
  padding: 16px 20px;
  border: 1px solid #e2e9f2;
  border-radius: 11px;
  background: #fff;
}

.modal-info-label {
  color: #444b56;
  font-size: 11px;
  line-height: 1.35;
  font-weight: 750;
  letter-spacing: 0.025em;
}

.modal-info-value {
  color: #142440;
  font-size: 13px;
  line-height: 1.35;
  font-weight: 750;
}

.modal-info-value span {
  font-weight: 500;
}

.modal-info-tag {
  padding: 4px 7px;
  border-radius: 4px;
  background: #fff0cf;
  color: #8b5700;
  font-size: 9px;
  line-height: 1.2;
  font-weight: 700;
}

.modal-info-sub {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #596273;
  font-size: 11px;
  line-height: 1.4;
}

.modal-info-sub svg {
  width: 13px;
  height: 13px;
  flex: none;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.modal-divider {
  height: 1px;
  margin: 27px 0;
  border: 0;
  background: #e1e8f1;
}

.rules-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 14px;
  color: #142440;
  font-size: 13px;
  line-height: 1.4;
  font-weight: 750;
}

.rules-heading svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #a56b00;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.rules-list {
  display: flex;
  flex-direction: column;
  gap: 11px;
  margin: 0;
  padding: 25px 31px;
  border-radius: 12px;
  background: #f5f7fe;
  list-style: none;
}

.rules-list li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.rule-number {
  width: 23px;
  height: 23px;
  flex: 0 0 23px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #e4ecfa;
  color: #162c50;
  font-size: 11px;
  font-weight: 750;
}

.rules-list strong {
  display: block;
  margin: 0;
  color: #142440;
  font-size: 11.5px;
  line-height: 1.45;
  font-weight: 750;
}

.rules-list p {
  max-width: 680px;
  margin: 2px 0 0;
  color: #535c6b;
  font-size: 11.5px;
  line-height: 1.5;
}

.agreement {
  width: fit-content;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 29px 10px 0;
  color: #142440;
  font-size: 11.5px;
  line-height: 1.45;
  font-weight: 600;
  cursor: pointer;
}

.agreement input {
  width: 16px;
  height: 16px;
  flex: 0 0 16px;
  margin: 0;
  accent-color: #f59e0b;
  cursor: pointer;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-top: 49px;
}

.modal-button {
  min-height: 39px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 9px 17px;
  border: 0;
  border-radius: 9px;
  font: inherit;
  font-size: 11.5px;
  line-height: 1.2;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease, opacity 0.15s ease;
}

.modal-button svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.button-cancel {
  background: #bd1e1e;
  color: #fff;
}

.button-cancel:hover {
  background: #a91919;
}

.button-confirm {
  background: #f59e0b;
  color: #fff;
}

.button-confirm:hover:not(:disabled) {
  background: #e99100;
}

.button-confirm:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.modal-button:focus-visible,
.agreement input:focus-visible {
  outline: 2px solid #183d70;
  outline-offset: 3px;
}

.fade-modal-enter-active,
.fade-modal-leave-active {
  transition: opacity 0.18s ease;
}

.fade-modal-enter-active .modal,
.fade-modal-leave-active .modal {
  transition: transform 0.18s ease;
}

.fade-modal-enter-from,
.fade-modal-leave-to {
  opacity: 0;
}

.fade-modal-enter-from .modal,
.fade-modal-leave-to .modal {
  transform: translateY(8px);
}

@media (max-width: 1000px) {
  .modal {
    padding: 30px 28px;
  }

  .modal-info-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .modal-footer {
    margin-top: 32px;
  }
}

@media (max-width: 760px) {
  .topbar {
    padding: 13px 16px;
  }

  .konten {
    padding: 22px 16px 38px;
  }

  .grid-level {
    grid-template-columns: 1fr;
    gap: 13px;
  }

  .level-card {
    min-height: 205px;
  }

  .section-divider-large {
    margin-top: 32px;
  }

  .discussion-card {
    align-items: stretch;
    flex-direction: column;
    gap: 16px;
  }

  .btn-discussion {
    width: 100%;
  }

  .answer-grid {
    grid-template-columns: repeat(12, 14px);
  }
}

@media (max-width: 560px) {
  .section-block > h2 {
    font-size: 19px;
  }

  .result-card {
    padding: 17px 14px;
  }

  .result-heading {
    align-items: flex-start;
    gap: 12px;
  }

  .score-ring {
    width: 60px;
    height: 60px;
    flex-basis: 60px;
  }

  .result-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .result-stat {
    min-height: 62px;
  }

  .answer-grid {
    grid-template-columns: repeat(10, 14px);
  }

  .overlay {
    align-items: flex-start;
    padding: 10px 8px;
  }

  .modal {
    max-height: calc(100vh - 20px);
    padding: 22px 15px 18px;
    border-radius: 12px;
  }

  .modal-header {
    align-items: flex-start;
    flex-direction: column;
    margin-bottom: 18px;
  }

  .modal-info-grid {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .modal-info-card {
    min-height: 100px;
    padding: 12px;
  }

  .modal-info-label {
    font-size: 9px;
  }

  .modal-info-value {
    font-size: 12px;
  }

  .modal-info-sub {
    font-size: 9px;
  }

  .modal-divider {
    margin: 20px 0;
  }

  .rules-list {
    gap: 12px;
    padding: 17px 13px;
  }

  .rules-list strong,
  .rules-list p {
    font-size: 10.5px;
  }

  .agreement {
    align-items: flex-start;
    margin: 20px 0 0;
    font-size: 10.5px;
  }

  .modal-footer {
    gap: 8px;
    margin-top: 25px;
  }

  .modal-button {
    min-height: 38px;
    padding: 9px 12px;
    font-size: 10.5px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fade-modal-enter-active,
  .fade-modal-leave-active,
  .fade-modal-enter-active .modal,
  .fade-modal-leave-active .modal {
    transition: none;
  }
}
</style>
