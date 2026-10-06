<template>
  <div class="page" :class="{ offline: offlineTampil }">
    <!-- HEADER -->
    <header class="header">
      <div class="header-left">
        <h1>{{ judul }}</h1>
        <p>{{ subjudul }}</p>
      </div>

      <div class="header-right">
        <span class="timer-label">Sisa Waktu :</span>
        <span class="timer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <circle cx="12" cy="13" r="8" /><path d="M12 9v4l2 2M9 2h6" />
          </svg>
          {{ waktuTampil }}
        </span>

        <div class="user-wrap">
          <button class="user" @click="menuUser = !menuUser">
            <span class="avatar">
              <svg viewBox="0 0 40 40" width="34" height="34">
                <rect width="40" height="40" fill="#dfe7f5" />
                <path d="M6 40c1-9 7-13 14-13s13 4 14 13z" fill="#2d2a8c" />
                <circle cx="20" cy="17" r="7.5" fill="#f2b48c" />
                <path d="M12.5 15c0-6 4-8.5 8-8.5s7.5 2.5 7 8.5c-2-3-5-4-7.500-4s-5 .8-7.500 4z" fill="#2b1d17" />
              </svg>
            </span>
            <span class="user-name">{{ auth.nama || 'Siswa' }}</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div v-if="menuUser" class="user-menu">
            <button @click="menuUser = false">Profil</button>
            <button @click="menuUser = false">Riwayat Hasil</button>
            <button @click="menuUser = false">Keluar</button>
          </div>
        </div>
      </div>
    </header>

    <!-- STATUS AWAL: PILIH TINGKAT / MEMUAT / GALAT -->
    <section
      v-if="!bolehMengerjakan && pretest.status !== STATUS.MENILAI"
      class="mulai-wrap"
    >
      <div class="card mulai">
        <template v-if="memuatAwal">
          <h2>Menyiapkan pre-test…</h2>
          <p class="mulai-desc">Menghubungi server.</p>
        </template>
        <template v-else-if="galatAwal">
          <h2>Gagal memuat pre-test</h2>
          <p class="mulai-desc">{{ galatAwal }}</p>
          <button class="btn btn-next" @click="cobaLagi">Coba Lagi</button>
        </template>
        <template v-else>
          <h2>Pilih Tingkat Pre-Test</h2>
          <p class="mulai-desc">Pre-test dikerjakan per tingkat. Tingkat yang terbuka bisa dimulai.</p>
          <div class="tingkat-list">
            <button
              v-for="t in pretest.tingkatList"
              :key="t.id"
              class="tingkat-opsi"
              :class="{ aktif: tingkatDipilih === t.id }"
              :disabled="!t.terbuka"
              @click="tingkatDipilih = t.id"
            >
              <b>{{ t.nama }}</b>
              <span>{{ t.terbuka ? 'Terbuka' : 'Terkunci' }}</span>
            </button>
          </div>
          <button
            class="btn btn-mulai"
            :disabled="!tingkatDipilih || pretest.loading"
            @click="mulaiDipilih"
          >
            {{ pretest.loading ? 'Memulai…' : 'Mulai Pre-Test' }}
          </button>
        </template>
      </div>
    </section>

    <!-- STATUS MENILAI: jawaban terkunci, nilai belum keluar -->
    <section v-else-if="pretest.status === STATUS.MENILAI" class="mulai-wrap">
      <div class="card mulai menilai">
        <h2>Jawabanmu sedang dinilai</h2>
        <p class="mulai-desc">
          Jawabanmu sudah dikumpulkan dan sedang dinilai. Halaman ini akan berpindah otomatis
          setelah nilai siap.
        </p>
      </div>
    </section>

    <!-- KONTEN -->
    <main v-else class="main">
      <section class="col-left">
        <!-- PETUNJUK -->
        <div v-if="petunjukTerbuka" class="card petunjuk">
          <div class="petunjuk-head">
            <h2>Petunjuk Pengerjaan</h2>
            <button class="close" aria-label="Tutup petunjuk" @click="petunjukTerbuka = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                <path d="M5 5l14 14M19 5L5 19" />
              </svg>
            </button>
          </div>
          <p class="petunjuk-desc">
            Pre-test ini memetakan kompetensi awalmu: waktu terbatas dan jawaban tidak bisa
            diubah setelah dikumpulkan. Setiap jawaban tersimpan otomatis ke server.
          </p>
          <div class="tipe-grid">
            <div v-for="t in tipeSoal" :key="t.nama" class="tipe" :class="t.kelas">
              <span class="tipe-label">TIPE SOAL</span>
              <strong>{{ t.nama }}</strong>
              <p>{{ t.desc }}</p>
            </div>
          </div>
        </div>

        <!-- SOAL -->
        <div v-if="soalAktif" class="card soal">
          <div class="soal-meta">
            <div class="soal-meta-left">
              <span class="chip chip-blue">Soal {{ aktif + 1 }} dari {{ total }} soal</span>
              <span class="chip chip-gray">{{ labelTipe }}</span>
            </div>
            <div class="soal-meta-right">
              <span v-if="sudahDijawab(aktif)" class="tersimpan">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12l5 5L20 6" />
                </svg>
                Tersimpan
              </span>
              <span v-if="pretest.simpanError" class="belum-tersimpan">Gagal menyimpan, periksa koneksi</span>
              <span class="bobot">Bobot {{ soalAktif.bobot }} poin</span>
            </div>
          </div>

          <div v-if="soalAktif.konteks" class="konteks">
            <strong>{{ soalAktif.konteks.judul }}</strong>
            <p>{{ soalAktif.konteks.isi }}</p>
            <img v-if="soalAktif.konteks.gambar" :src="soalAktif.konteks.gambar" alt="Gambar konteks soal" />
          </div>
          <img v-if="soalAktif.gambar" :src="soalAktif.gambar" class="gambar-soal" alt="Gambar soal" />
          <h3 class="pertanyaan" v-html="soalAktif.pertanyaan"></h3>
          <p class="hint">{{ petunjukTipe }}</p>

          <!-- Pilihan Ganda -->
          <div v-if="soalAktif.tipe === 'ganda'" class="opsi-list">
            <button
              v-for="o in soalAktif.opsi"
              :key="o.kode"
              class="opsi"
              :class="{ aktif: jawaban[aktif] === o.kode }"
              @click="pilihGanda(o.kode)"
            >
              <span class="radio"><span v-if="jawaban[aktif] === o.kode" class="dot"></span></span>
              <b>{{ o.kode }}.</b>
              <span>{{ o.teks }}</span>
            </button>
          </div>

          <!-- Isian -->
          <div v-else class="opsi-list">
            <textarea
              class="uraian"
              rows="7"
              placeholder="Tulis jawabanmu di sini..."
              :value="jawaban[aktif] || ''"
              @input="isiUraian($event.target.value)"
            ></textarea>
          </div>

          <div class="nav">
            <button class="btn btn-prev" :disabled="aktif === 0" @click="pindah(aktif - 1)">
              <small>←</small> Sebelumnya
            </button>
            <button class="btn btn-ragu" @click="toggleRagu">
              <span class="cb" :class="{ on: ragu[aktif] }">
                <svg v-if="ragu[aktif]" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12l5 5L20 6" />
                </svg>
              </span>
              Ragu
            </button>
            <button v-if="tombolSelesai" class="btn btn-next" @click="modalSelesai = true">
              Selesai <small>→</small>
            </button>
            <button v-else class="btn btn-next" @click="pindah(aktif + 1)">
              Berikutnya <small>→</small>
            </button>
          </div>
        </div>
      </section>

      <!-- RINGKASAN -->
      <aside class="col-right">
        <div class="card ringkasan">
          <div class="ringkasan-head">
            <h2>RINGKASAN STATUS SOAL</h2>
            <span class="badge">{{ total }} Butir</span>
          </div>

          <div class="stat-grid">
            <div class="stat stat-green"><b>{{ jmlTerjawab }}</b><span>Terjawab</span></div>
            <div class="stat stat-orange"><b>{{ jmlRagu }}</b><span>Ragu-ragu</span></div>
            <div class="stat stat-gray"><b>{{ jmlBelum }}</b><span>Belum</span></div>
          </div>

          <p class="nomer-label">Nomer Soal :</p>
          <div class="nomer-grid">
            <button
              v-for="(s, i) in soal"
              :key="i"
              class="nomer"
              :class="kelasNomer(i)"
              @click="pindah(i)"
            >
              {{ String(i + 1).padStart(2, '0') }}
            </button>
          </div>

          <div class="legend">
            <span><i class="lg lg-green"></i>Terjawab ({{ jmlTerjawab }})</span>
            <span><i class="lg lg-yellow"></i>Ragu-ragu ({{ jmlRagu }})</span>
            <span><i class="lg lg-open"></i>Sedang dibuka</span>
            <span><i class="lg lg-empty"></i>Belum dijawab ({{ jmlBelum }})</span>
          </div>
        </div>
      </aside>
    </main>

    <!-- NOTIFIKASI KONEKSI -->
    <div v-if="offlineTampil" class="overlay-offline"></div>
    <transition name="toast">
      <div v-if="offlineTampil" class="toast" :class="'toast-' + koneksi" role="alert">
        <span class="toast-icon">
          <svg v-if="koneksi === 'terhubung'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12.5l4.5 4.5L19 7.5" />
          </svg>
          <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a15 15 0 0 1 20 0" /><path d="M5.5 12.6a10 10 0 0 1 13 0" /><path d="M9 16.2a5 5 0 0 1 6 0" /><circle cx="12" cy="19.5" r=".8" fill="#fff" />
          </svg>
        </span>
        <div class="toast-text">
          <strong>{{ teksToast.judul }}</strong>
          <p>{{ teksToast.isi }}</p>
        </div>
        <button class="toast-close" aria-label="Tutup notifikasi" @click="tutupToast">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
            <path d="M5 5l14 14M19 5L5 19" />
          </svg>
        </button>
      </div>
    </transition>

    <!-- MODAL SELESAI -->
    <div v-if="modalSelesai" class="overlay" @click.self="!mengirim && (modalSelesai = false)">
      <div class="modal" role="dialog" aria-modal="true">
          <div class="modal-head">
            <span class="modal-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9.5" /><path d="M9.2 9.3a2.9 2.9 0 1 1 4.3 2.5c-.9.5-1.5 1-1.5 2" /><circle cx="12" cy="16.9" r=".6" fill="currentColor" />
              </svg>
            </span>
            <div>
              <h3>Yakin ingin mengumpulkan pre-test?</h3>
              <p>Kamu masih memiliki {{ jmlBelum }} soal yang belum dijawab dan {{ jmlRagu }} soal yang ditandai.</p>
            </div>
          </div>

          <div class="modal-stats">
            <div class="mstat mstat-green"><b>{{ jmlTerjawab }}</b><span>Terjawab</span></div>
            <div class="mstat mstat-orange"><b>{{ jmlRagu }}</b><span>Ragu-ragu</span></div>
            <div class="mstat mstat-gray"><b>{{ jmlBelum }}</b><span>Belum</span></div>
          </div>

          <div class="modal-warning">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="9.5" /><path d="M12 11v5.5" /><circle cx="12" cy="7.8" r=".6" fill="currentColor" />
            </svg>
            <span>Setelah pre-test dikumpulkan, jawaban tidak dapat diubah kembali.</span>
          </div>

          <div class="modal-actions">
            <button class="mbtn mbtn-yellow" :disabled="mengirim" @click="modalSelesai = false">Kembali Mengerjakan</button>
            <button class="mbtn mbtn-navy" :disabled="mengirim" @click="kumpulkan">{{ mengirim ? 'Mengumpulkan…' : 'Kumpulkan Jawaban' }}</button>
          </div>
      </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-inner">
        <span>© 2026 SIAP OSN. Seluruh hak cipta dilindungi.</span>
        <span>Malang, Indonesia</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth.js'
import { useProgressStore } from '@/stores/progress.js'
import { usePretestStore, STATUS } from '@/stores/pretest.js'
import { pesanError } from '@/lib/errors.js'

// true = tombol kanan selalu "Selesai" (seperti pada tampilan modal konfirmasi)
const props = defineProps({
  tampilkanSelesai: { type: Boolean, default: false },
  judul: { type: String, default: 'Pre-Test' },
  subjudul: { type: String, default: 'Pemetaan kompetensi awal.' },
  // true = langsung tampilkan kondisi "Koneksi Terputus" (untuk pratinjau)
  simulasiOffline: { type: Boolean, default: false },
  // status awal koneksi untuk pratinjau: 'online' | 'offline' | 'sinkron' | 'terhubung'
  statusAwal: { type: String, default: 'online' },
})

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const progress = useProgressStore()
const pretest = usePretestStore()

const tipeSoal = [
  { nama: 'Pilihan Ganda', kelas: 'tipe-green', desc: 'Pilih satu jawaban yang paling tepat dari beberapa opsi.' },
  { nama: 'Isian', kelas: 'tipe-red', desc: 'Tulis jawabanmu dengan kalimat sendiri, jelaskan langkah dan alasannya.' },
]

/* ---------- Sumber soal: store (backend) ---------- */
const soal = computed(() => pretest.soal)
const total = computed(() => soal.value.length)
const bolehMengerjakan = computed(() => pretest.status === STATUS.MENGERJAKAN)

/* ---------- Polling nilai (status assessing) ---------- */
let timerPolling = null
function stopPolling() {
  clearInterval(timerPolling)
  timerPolling = null
}
function mulaiPolling() {
  stopPolling()
  timerPolling = setInterval(async () => {
    const hasil = await pretest.cekHasil()
    if (!hasil) return
    stopPolling()
    progress.markPreTestCompleted()
    await progress.fetchDashboard({ force: true }).catch(() => {})
    router.push({ name: 'siswa.pemetaan', params: { id: pretest.pretestId } })
  }, 5000)
}

/* ---------- State awal: pilih tingkat ---------- */
const tingkatDipilih = ref(null)
const memuatAwal = ref(true)
const galatAwal = ref('')
const mengirim = ref(false)

/* ---------- State pengerjaan (UI lokal) ---------- */
const aktif = ref(0)
const petunjukTerbuka = ref(true)
const menuUser = ref(false)

// Jawaban per indeks soal (disalin dari store saat mulai/resume).
// ganda: kode opsi ('A'), isian: string bebas. Ragu-ragu murni lokal.
const jawaban = reactive({})
const ragu = reactive({})

const soalAktif = computed(() => soal.value[aktif.value] ?? null)

/* ---------- Helper ---------- */
function sudahDijawab(i) {
  const j = jawaban[i]
  if (j === undefined || j === null) return false
  if (Array.isArray(j)) return j.length > 0
  if (typeof j === 'string') return j.trim().length > 0
  return true
}

const jmlTerjawab = computed(() => soal.value.filter((_, i) => sudahDijawab(i)).length)
const jmlRagu = computed(() => soal.value.filter((_, i) => ragu[i]).length)
const jmlBelum = computed(() => total.value - jmlTerjawab.value - soal.value.filter((_, i) => ragu[i] && !sudahDijawab(i)).length)

function kelasNomer(i) {
  return {
    'n-open': i === aktif.value,
    'n-ragu': i !== aktif.value && ragu[i],
    'n-done': i !== aktif.value && !ragu[i] && sudahDijawab(i),
    'n-empty': i !== aktif.value && !ragu[i] && !sudahDijawab(i),
  }
}

const labelTipe = computed(() => {
  if (soalAktif.value?.tipe === 'ganda') return 'Pilihan Ganda · pilih 1'
  return 'Isian · tulis jawabanmu'
})
const petunjukTipe = computed(() => {
  if (soalAktif.value?.tipe === 'ganda') return 'Pilih satu jawaban yang paling tepat.'
  return 'Tulis jawabanmu beserta langkah dan alasannya.'
})

/* ---------- Mulai / resume ---------- */
function seedJawaban() {
  for (const k of Object.keys(jawaban)) delete jawaban[k]
  for (const k of Object.keys(ragu)) delete ragu[k]
  soal.value.forEach((s, i) => {
    jawaban[i] = s.jawaban ?? null
  })
  aktif.value = 0
}

function pilihDefaultTingkat() {
  const aktifId = progress.data.tingkatAktifId
  const daftar = pretest.tingkatList
  const cocok = daftar.find((t) => t.id === aktifId && t.terbuka) ?? daftar.find((t) => t.terbuka) ?? null
  tingkatDipilih.value = cocok?.id ?? null
}

async function siapkanAwal() {
  memuatAwal.value = true
  galatAwal.value = ''
  // Store masih hangat (pindah halaman tanpa reload) -> langsung pakai.
  if (pretest.status === STATUS.MENGERJAKAN && pretest.soal.length) {
    seedJawaban()
    memuatAwal.value = false
    return
  }
  // Resume bila ada ID tersimpan (reload di tengah ujian).
  const tersimpan = pretest.idTersimpan()
  if (tersimpan?.id) {
    try {
      await pretest.lanjutkan({ id: tersimpan.id })
    } catch {
      // ID sudah dibuang store (404) -> lanjut ke pemilih tingkat di bawah.
      if (pretest.idTersimpan()) {
        galatAwal.value = 'Tidak dapat membuka pre-test tersimpan. Coba lagi.'
        memuatAwal.value = false
        return
      }
    }
    if (pretest.status === STATUS.SELESAI) {
      router.replace({ name: 'siswa.pemetaan', params: { id: pretest.pretestId } })
      return
    }
    if (pretest.status === STATUS.MENILAI) {
      mulaiPolling()
      memuatAwal.value = false
      return
    }
    if (pretest.status === STATUS.MENGERJAKAN) {
      seedJawaban()
      memuatAwal.value = false
      return
    }
  }
  // Belum ada yang berjalan -> pemilih tingkat manual.
  try {
    await pretest.muatTingkat()
    await progress.fetchDashboard().catch(() => {})
    pilihDefaultTingkat()
  } catch {
    galatAwal.value = 'Tidak dapat memuat daftar tingkat. Periksa koneksi lalu coba lagi.'
  }
  memuatAwal.value = false
}

function cobaLagi() {
  siapkanAwal()
}

async function mulaiDipilih() {
  if (!tingkatDipilih.value || pretest.loading) return
  try {
    await pretest.mulai({ tingkatId: tingkatDipilih.value })
    seedJawaban()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal memulai pre-test', detail: pesanError(err), life: 4000 })
  }
}

/* ---------- Kumpulkan ---------- */
const modalSelesai = ref(false)
// "Selesai" tampil di soal terakhir, atau di semua soal bila prop tampilkanSelesai = true
const tombolSelesai = computed(() => props.tampilkanSelesai || aktif.value === total.value - 1)

async function kumpulkan() {
  if (mengirim.value || pretest.status !== STATUS.MENGERJAKAN) return
  mengirim.value = true
  try {
    const hasil = await pretest.kumpulkan()
    modalSelesai.value = false
    if (!hasil) {
      // 503 HASIL_SEDANG_DIPROSES: jawaban sudah terkunci, tunggu nilai.
      mulaiPolling()
      return
    }
    progress.markPreTestCompleted()
    await progress.fetchDashboard({ force: true }).catch(() => {})
    router.push({ name: 'siswa.pemetaan', params: { id: pretest.pretestId } })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal mengumpulkan', detail: pesanError(err), life: 4000 })
    modalSelesai.value = false
  } finally {
    mengirim.value = false
  }
}

/* ---------- Aksi jawab + autosave (debounce per soal) ---------- */
const timerSimpan = {}
function jadwalSimpan(i) {
  clearTimeout(timerSimpan[i])
  timerSimpan[i] = setTimeout(() => {
    const target = soal.value[i]
    if (!target || pretest.status !== STATUS.MENGERJAKAN) return
    const nilai = jawaban[i]
    pretest.simpanJawaban({ soalId: target.id, jawaban: nilai === '' ? null : (nilai ?? null) })
  }, 800)
}
function pilihGanda(kode) {
  if (pretest.status !== STATUS.MENGERJAKAN) return
  jawaban[aktif.value] = kode
  jadwalSimpan(aktif.value)
}
function isiUraian(v) {
  if (pretest.status !== STATUS.MENGERJAKAN) return
  jawaban[aktif.value] = v
  jadwalSimpan(aktif.value)
}
function toggleRagu() {
  if (pretest.status !== STATUS.MENGERJAKAN) return
  ragu[aktif.value] = !ragu[aktif.value]
}
function pindah(i) {
  if (i < 0 || i >= total.value) return
  aktif.value = i
}

/* ---------- Timer (90 menit) ---------- */
const sisaDetik = ref(90 * 60)
let timer = null
const waktuTampil = computed(() => {
  const m = Math.floor(sisaDetik.value / 60)
  const s = sisaDetik.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})
onMounted(() => {
  siapkanAwal()
  timer = setInterval(() => {
    if (sisaDetik.value > 0) {
      sisaDetik.value--
      // Batas waktu client-side (backend tidak enforce): habis -> kumpulkan otomatis.
      if (sisaDetik.value === 0 && pretest.status === STATUS.MENGERJAKAN) {
        kumpulkan()
      }
    }
  }, 1000)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  stopPolling()
  for (const k of Object.keys(timerSimpan)) clearTimeout(timerSimpan[k])
})

/* ---------- Status koneksi ---------- */
// koneksi: 'online' | 'offline' | 'sinkron' | 'terhubung'
// alur: offline -> sinkron (menyinkronkan) -> terhubung (berhasil) -> online
const koneksi = ref(props.simulasiOffline ? 'offline' : props.statusAwal)
const offlineTampil = ref(koneksi.value !== 'online')
let timerSinkron = null

const teksToast = computed(() => ({
  offline: { judul: 'Koneksi Terputus !!!', isi: 'Jawaban tetap tersimpan lokal secara aman di memori perangkat kamu...' },
  sinkron: { judul: 'Koneksi Kembali !!!', isi: 'Sedang menyinkronkan kembali...' },
  terhubung: { judul: 'Berhasil Terkoneksi !!!', isi: 'Berhasil terhubung ke jaringan...' },
}[koneksi.value] || { judul: '', isi: '' }))

function setOffline() {
  clearTimeout(timerSinkron)
  koneksi.value = 'offline'
  offlineTampil.value = true
}
function setOnline() {
  koneksi.value = 'sinkron'
  offlineTampil.value = true
  clearTimeout(timerSinkron)
  // 1) menyinkronkan  ->  2) berhasil terhubung  ->  3) notifikasi dan efek buram hilang
  timerSinkron = setTimeout(() => {
    koneksi.value = 'terhubung'
    timerSinkron = setTimeout(() => {
      koneksi.value = 'online'
      offlineTampil.value = false
    }, 2500)
  }, 2500)
}
function tutupToast() {
  clearTimeout(timerSinkron)
  offlineTampil.value = false
}

onMounted(() => {
  window.addEventListener('offline', setOffline)
  window.addEventListener('online', setOnline)
  if (!navigator.onLine) setOffline()
})
onBeforeUnmount(() => {
  clearTimeout(timerSinkron)
  window.removeEventListener('offline', setOffline)
  window.removeEventListener('online', setOnline)
})
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }
body { margin: 0; }
</style>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f5f8fd;
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
button { font-family: inherit; cursor: pointer; }

/* Header */
.header {
  height: 86px;
  background: #fff;
  border-bottom: 1px solid #e6ebf3;
  padding: 0 70px 0 67px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.header h1 { margin: 0; font-size: 20px; font-weight: 600; color: #0b1220; }
.header p { margin: 4px 0 0; font-size: 14px; color: #8a94a6; }
.header-right { display: flex; align-items: center; gap: 10px; }
.timer-label { font-size: 14px; color: #8a94a6; margin-right: 2px; }
.timer {
  display: inline-flex; align-items: center; gap: 7px;
  background: #fde9ec; color: #d91c4a;
  font-weight: 600; font-size: 13.5px;
  padding: 7px 14px; border-radius: 999px;
  margin-right: 14px;
}
.user-wrap { position: relative; }
.user {
  display: flex; align-items: center; gap: 10px;
  background: #eef1f6; border: 1px solid #d9dfea;
  padding: 4px 14px 4px 5px; border-radius: 999px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
  color: #0b1220;
}
.avatar { width: 38px; height: 38px; border-radius: 50%; overflow: hidden; background: #fff; display: grid; place-items: center; }
.user-name { font-size: 16px; font-weight: 500; }
.user-menu {
  position: absolute; right: 0; top: calc(100% + 8px); z-index: 10;
  background: #fff; border: 1px solid #e3e8f1; border-radius: 12px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  padding: 6px; min-width: 160px; display: flex; flex-direction: column;
}
.user-menu button { background: none; border: 0; text-align: left; padding: 9px 12px; border-radius: 8px; font-size: 14px; color: #1e293b; }
.user-menu button:hover { background: #f1f5fb; }

/* Layout */
.main {
  flex: 1;
  width: 100%;
  max-width: 1440px;
  margin: 0 auto;
  padding: 26px 73px 34px 70px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 374px;
  gap: 19px;
  align-items: start;
}
.col-left { display: flex; flex-direction: column; gap: 24px; }
.card {
  background: #fff;
  border: 1px solid #e8ecf3;
  border-radius: 22px;
  box-shadow: 0 2px 10px rgba(30, 50, 100, 0.04);
}

/* Petunjuk */
.petunjuk {
  border-color: #fbd9ae;
  background: #fffefc;
  padding: 36px 30px 35px;
}
.petunjuk-head { display: flex; justify-content: space-between; align-items: center; }
.petunjuk h2 { margin: 0; font-size: 18px; font-weight: 600; color: #0b1220; }
.close { background: none; border: 0; color: #c8202f; padding: 2px; display: grid; place-items: center; }
.petunjuk-desc { margin: 12px 0 22px; font-size: 14.5px; line-height: 1.6; color: #4b5563; }
.tipe-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.tipe {
  background: #fff6ec;
  border: 1px solid #fbe3c6;
  border-radius: 14px;
  padding: 22px 16px 18px;
}
.tipe-label { display: block; font-size: 12.5px; letter-spacing: 0.9px; color: #4b5563; margin-bottom: 8px; }
.tipe strong { display: block; font-size: 16px; font-weight: 600; color: #0b1220; margin-bottom: 10px; }
.tipe p { margin: 0; font-size: 12.5px; line-height: 1.55; color: #6b7280; }

/* Soal */
.soal { padding: 36px 33px 36px; }
.soal-meta {
  display: flex; justify-content: space-between; align-items: center;
  padding-bottom: 16px; border-bottom: 1px solid #eef1f6;
}
.soal-meta-left { display: flex; gap: 8px; }
.chip { font-size: 12.5px; padding: 5px 11px; border-radius: 7px; font-weight: 500; }
.chip-blue { background: #e8f0fe; color: #1d4ed8; font-weight: 600; }
.chip-gray { background: #f3f5f9; color: #374151; }
.soal-meta-right { display: flex; align-items: center; gap: 12px; font-size: 12.5px; }
.tersimpan { color: #0f9a55; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; }
.bobot { color: #6b7280; }

.pertanyaan { margin: 28px 0 6px; font-size: 17px; line-height: 1.3; font-weight: 600; color: #0b1220; max-width: 790px; }
.hint { margin: 6px 0 14px; font-size: 12.5px; color: #8a94a6; }

.opsi-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
.opsi {
  display: flex; align-items: center; gap: 10px;
  width: 100%; text-align: left;
  height: 50px; padding: 0 14px;
  background: #fff; border: 1px solid #e3e8f1; border-radius: 10px;
  font-size: 15px; color: #1e293b;
  box-shadow: 0 1px 3px rgba(30, 50, 100, 0.06);
  transition: border-color 0.15s, background 0.15s;
}
.opsi:hover { border-color: #b8c9ee; }
.opsi b { font-weight: 600; color: #1e3a8a; }
.opsi.aktif { border-color: #3b6fe0; background: #f6f9ff; }
.radio {
  width: 18px; height: 18px; border-radius: 50%;
  border: 1.5px solid #cbd2de; background: #fff;
  display: grid; place-items: center; flex-shrink: 0;
}
.opsi.aktif .radio { border-color: #2563eb; background: #2563eb; }
.dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; }
.check {
  width: 18px; height: 18px; border-radius: 5px;
  border: 1.5px solid #cbd2de; background: #fff;
  display: grid; place-items: center; flex-shrink: 0;
}
.check.on { background: #2563eb; border-color: #2563eb; }
.uraian {
  width: 100%; border: 1px solid #e3e8f1; border-radius: 10px;
  padding: 14px; font: inherit; font-size: 15px; resize: vertical;
  outline: none; color: #1e293b;
}
.uraian:focus { border-color: #3b6fe0; background: #f6f9ff; }

/* Navigasi */
.nav {
  display: flex; justify-content: space-between; align-items: center;
  border-top: 1px solid #eef1f6; padding-top: 30px; margin-top: 4px;
}
.btn {
  height: 38px; padding: 0 15px; border-radius: 7px;
  font-size: 13px; font-weight: 500;
  display: inline-flex; align-items: center; gap: 5px;
  transition: filter 0.15s, transform 0.05s;
}
.btn small { font-size: 11px; }
.btn:hover:not(:disabled) { filter: brightness(0.97); }
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-prev { background: #fde4e4; border: 1px solid #ef6b6b; color: #b91c1c; }
.btn-ragu { background: #fff3e0; border: 1px solid #f2ab57; color: #c2610c; padding: 0 24px; }
.btn-next { background: #fff; border: 1px solid #3b6fe0; color: #1d4ed8; }
.cb {
  width: 11px; height: 11px; border: 1.2px solid #c2610c; border-radius: 2px;
  background: #fff; display: grid; place-items: center;
}
.cb.on { background: #c2610c; }

/* Ringkasan */
.ringkasan { padding: 42px 35px 40px 35px; border-radius: 22px; }
.ringkasan-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
.ringkasan h2 { margin: 0; font-size: 16.5px; font-weight: 600; letter-spacing: 0.3px; color: #2a3a52; }
.badge { background: #fde7c4; color: #b45309; font-size: 12.5px; font-weight: 600; padding: 4px 10px; border-radius: 7px; }

.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
.stat {
  height: 78px; border-radius: 14px; border: 1px solid;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
}
.stat b { font-size: 20px; font-weight: 700; line-height: 1; }
.stat span { font-size: 12px; }
.stat-green { background: #f3fcf6; border-color: #a7e6bf; color: #15803d; }
.stat-orange { background: #fff8ef; border-color: #f6c58a; color: #c2410c; }
.stat-gray { background: #fff; border-color: #e3e8f1; color: #1f2937; }

.nomer-label { margin: 18px 0 14px; font-size: 14px; color: #6b7280; }
.nomer-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 9px; }
.nomer {
  height: 54px; border-radius: 9px; border: 1px solid #e8ecf3;
  font-size: 13px; font-weight: 600; background: #fff; color: #b45309;
  transition: transform 0.08s, filter 0.15s;
}
.nomer:hover { filter: brightness(0.96); }
.nomer:active { transform: scale(0.96); }
.n-done { background: #c9f5d9; border-color: #8fe0ae; color: #15803d; }
.n-ragu { background: #fde8c8; border-color: #f0a957; color: #b45309; }
.n-open { background: #eaf1ff; border: 2px solid #2563eb; color: #1d4ed8; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15); }
.n-empty { background: #fff; border-color: #eaeef5; color: #b45309; }

.legend {
  margin-top: 22px; padding-top: 22px; border-top: 1px solid #eef1f6;
  display: grid; grid-template-columns: 1fr 1fr; row-gap: 10px; column-gap: 12px;
  font-size: 11.5px; color: #4b5563;
}
.legend span { display: flex; align-items: center; gap: 7px; padding-left: 20px; }
.lg { width: 11px; height: 11px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.lg-green { background: #c9f5d9; }
.lg-yellow { background: #fde8c8; }
.lg-open { background: #fff; border: 2px solid #2563eb; }
.lg-empty { background: #fff; border: 1px solid #eaeef5; }

/* Warna tipe soal */
.tipe-green { background: #d9f7e6; border-color: #b4ebcc; }
.tipe-orange { background: #fff6ec; border-color: #fbe3c6; }
.tipe-red { background: #fde2e6; border-color: #f8c4cc; }

/* Koneksi terputus */
.overlay-offline {
  position: fixed; inset: 0; z-index: 40;
  background: rgba(210, 217, 230, 0.55);
  backdrop-filter: blur(3px);
}
.page.offline .footer { position: relative; z-index: 41; filter: blur(1.5px); }
.toast {
  position: fixed; top: 103px; right: 55px; z-index: 60;
  display: flex; align-items: center; gap: 14px;
  border-radius: 12px; border: 1px solid;
}
.toast-offline {
  width: 382px; padding: 18px 18px 18px 22px;
  background: #fff1f2; border-color: #e0383e;
  box-shadow: 0 8px 24px rgba(190, 30, 45, 0.12);
}
.toast-offline .toast-icon { background: #c8322e; }
.toast-offline .toast-text p, .toast-offline .toast-close { color: #b91c1c; }
.toast-sinkron {
  top: 102px; width: 316px; padding: 12px 16px 12px 22px;
  background: #fffbea; border-color: #f2b705;
  box-shadow: 0 8px 24px rgba(180, 120, 0, 0.12);
}
.toast-sinkron .toast-icon { background: #e8a30a; }
.toast-sinkron .toast-text p, .toast-sinkron .toast-close { color: #b45309; }
.toast-terhubung {
  top: 102px; width: 316px; padding: 12px 16px 12px 22px;
  background: #ecfdf5; border-color: #86efc0;
  box-shadow: 0 8px 24px rgba(16, 150, 90, 0.12);
}
.toast-terhubung .toast-icon { background: #12b76a; width: 28px; height: 28px; border-radius: 50%; }
.toast-terhubung .toast-text p, .toast-terhubung .toast-close { color: #0f7a4a; }
.toast-icon {
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
  display: grid; place-items: center;
}
.toast-text { flex: 1; min-width: 0; }
.toast-text strong { display: block; font-size: 13px; font-weight: 700; color: #14532d; margin-bottom: 2px; }
.toast-text p { margin: 0; font-size: 12.5px; line-height: 1.4; }
.toast-close { align-self: flex-start; background: none; border: 0; padding: 2px; margin-top: -2px; }
.toast-enter-active, .toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }

/* Footer */
.footer { background: #0b1a3a; height: 65px; display: flex; align-items: center; }
.footer-inner {
  width: 100%; max-width: 930px; margin: 0 auto;
  display: flex; justify-content: space-between;
  font-size: 13px; color: #8b97b3;
}

/* Modal */
.overlay {
  position: fixed; inset: 0; z-index: 50; padding: 20px;
  background: rgba(30, 45, 90, 0.5);
  backdrop-filter: blur(3px);
  display: grid; place-items: center;
}
.modal {
  background: #fff; border-radius: 12px;
  width: 100%; max-width: 512px;
  padding: 32px 32px 32px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.3);
}
.modal-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 26px; }
.modal-icon {
  width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
  background: #fdebc8; color: #7a4a00;
  display: grid; place-items: center;
}
.modal-icon-ok { background: #d6f5e1; color: #15803d; }
.modal h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0b1220; line-height: 1.4; }
.modal-head p { margin: 2px 0 0; font-size: 15.5px; line-height: 1.55; color: #4b5563; }
.modal-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 26px; }
.mstat {
  height: 78px; border-radius: 10px; border: 1px solid;
  background: #fff;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;
}
.mstat b { font-size: 18px; font-weight: 700; line-height: 1; }
.mstat span { font-size: 12px; }
.mstat-green { border-color: #aeead0; color: #15803d; }
.mstat-orange { border-color: #f6c58a; color: #c2410c; }
.mstat-gray { border-color: #e3e8f1; color: #374151; }
.mstat-gray b { color: #374151; }
.modal-warning {
  display: flex; align-items: center; gap: 12px;
  background: #ffe3e5; border-radius: 8px;
  padding: 16px 20px; margin-bottom: 30px;
  font-size: 15.5px; line-height: 1.5; color: #3f3f46;
}
.modal-warning svg { color: #b45309; flex-shrink: 0; }
.modal-actions { display: flex; justify-content: center; gap: 8px; }
.mbtn {
  height: 44px; padding: 0 24px; border: 0; border-radius: 10px;
  font-size: 15.5px; font-weight: 600; color: #fff;
  transition: filter 0.15s, transform 0.05s;
}
.mbtn:hover { filter: brightness(0.95); }
.mbtn:active { transform: translateY(1px); }
.mbtn-yellow { background: #fbb024; }
.mbtn-navy { background: #1e3a8a; }

/* Layar awal: pilih tingkat */
.mulai-wrap { flex: 1; width: 100%; max-width: 1440px; margin: 0 auto; padding: 26px 73px 34px 70px; }
.mulai { padding: 40px 36px; max-width: 640px; }
.mulai h2 { margin: 0; font-size: 19px; font-weight: 600; color: #0b1220; }
.mulai-desc { margin: 10px 0 22px; font-size: 14px; line-height: 1.6; color: #4b5563; }
.tingkat-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
.tingkat-opsi {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; background: #fff; border: 1px solid #e3e8f1; border-radius: 10px;
  font-size: 15px; color: #1e293b; text-align: left;
}
.tingkat-opsi b { font-weight: 600; }
.tingkat-opsi span { font-size: 12.5px; color: #6b7280; }
.tingkat-opsi.aktif { border-color: #3b6fe0; background: #f6f9ff; }
.tingkat-opsi:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-mulai { background: #1e3a8a; border: 0; color: #fff; height: 44px; padding: 0 28px; font-size: 15px; font-weight: 600; }
.mulai.menilai { max-width: 520px; text-align: center; }

/* Konteks & gambar soal dari backend */
.konteks {
  margin: 20px 0 6px; padding: 16px 18px;
  background: #f6f9ff; border: 1px solid #dbe6fb; border-radius: 12px;
}
.konteks strong { display: block; font-size: 14px; color: #0b1220; margin-bottom: 6px; }
.konteks p { margin: 0; font-size: 14px; line-height: 1.6; color: #374151; white-space: pre-line; }
.konteks img { max-width: 100%; border-radius: 8px; margin-top: 10px; }
.gambar-soal { max-width: 100%; border-radius: 10px; margin-top: 16px; }
.belum-tersimpan { color: #b45309; font-weight: 500; }

/* Responsif */
@media (max-width: 1000px) {
  .main { grid-template-columns: 1fr; padding: 20px; }
  .header { padding: 0 20px; }
  .tipe-grid { grid-template-columns: 1fr; }
  .timer-label { display: none; }
}
</style>
