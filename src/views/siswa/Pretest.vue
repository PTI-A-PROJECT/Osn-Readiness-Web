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

    <!-- KONTEN -->
    <main class="main">
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
            Simulasi ini meniru kondisi seleksi tingkat provinsi: waktu terbatas dan jawaban tidak bisa
            diubah setelah dikumpulkan. Status selesai dicatat di sesi ini; nilai dan riwayat hasil
            memerlukan integrasi backend.
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
        <div class="card soal">
          <div class="soal-meta">
            <div class="soal-meta-left">
              <span class="chip chip-blue">Soal {{ aktif + 1 }} dari {{ total }} soal</span>
              <span class="chip chip-gray">{{ labelTipe }}</span>
            </div>
            <div class="soal-meta-right">
              <span v-if="soalAktif.status === 'terjawab' || sudahDijawab(aktif)" class="tersimpan">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12l5 5L20 6" />
                </svg>
                Tersimpan
              </span>
              <span class="bobot">Bobot {{ soalAktif.bobot }} poin</span>
            </div>
          </div>

          <h3 class="pertanyaan" v-html="soalAktif.teks"></h3>
          <p class="hint">{{ petunjukTipe }}</p>

          <!-- Pilihan Ganda -->
          <div v-if="soalAktif.tipe === 'ganda'" class="opsi-list">
            <button
              v-for="(o, i) in soalAktif.opsi"
              :key="i"
              class="opsi"
              :class="{ aktif: jawaban[aktif] === i }"
              @click="pilihGanda(i)"
            >
              <span class="radio"><span v-if="jawaban[aktif] === i" class="dot"></span></span>
              <b>{{ huruf[i] }}.</b>
              <span>{{ o }}</span>
            </button>
          </div>

          <!-- Pilihan Kompleks -->
          <div v-else-if="soalAktif.tipe === 'kompleks'" class="opsi-list">
            <button
              v-for="(o, i) in soalAktif.opsi"
              :key="i"
              class="opsi"
              :class="{ aktif: (jawaban[aktif] || []).includes(i) }"
              @click="pilihKompleks(i)"
            >
              <span class="check" :class="{ on: (jawaban[aktif] || []).includes(i) }">
                <svg v-if="(jawaban[aktif] || []).includes(i)" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 12l5 5L20 6" />
                </svg>
              </span>
              <b>{{ huruf[i] }}.</b>
              <span>{{ o }}</span>
            </button>
          </div>

          <!-- Uraian -->
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
            <button v-if="tombolSelesai" class="btn btn-next" :disabled="dikumpulkan" @click="modalSelesai = true">
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
    <div v-if="modalSelesai" class="overlay" @click.self="!dikumpulkan && (modalSelesai = false)">
      <div class="modal" role="dialog" aria-modal="true">
        <template v-if="!dikumpulkan">
          <div class="modal-head">
            <span class="modal-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9.5" /><path d="M9.2 9.3a2.9 2.9 0 1 1 4.3 2.5c-.9.5-1.5 1-1.5 2" /><circle cx="12" cy="16.9" r=".6" fill="currentColor" />
              </svg>
            </span>
            <div>
              <h3>Yakin ingin mengumpulkan simulasi?</h3>
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
            <span>Setelah simulasi dikumpulkan, jawaban tidak dapat diubah kembali.</span>
          </div>

          <div class="modal-actions">
            <button class="mbtn mbtn-yellow" @click="modalSelesai = false">Kembali Mengerjakan</button>
            <button class="mbtn mbtn-navy" @click="kumpulkan">Kumpulkan Jawaban</button>
          </div>
        </template>

        <template v-else>
          <div class="modal-head">
            <span class="modal-icon modal-icon-ok">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9.5" /><path d="M7.8 12.3l3 3 5.6-6" />
              </svg>
            </span>
            <div>
              <h3>Jawaban sudah dikumpulkan</h3>
              <p>Kamu menjawab {{ jmlTerjawab }} dari {{ total }} soal. Status selesai dicatat di sesi ini.</p>
            </div>
          </div>
          <div class="modal-actions">
            <button class="mbtn mbtn-navy" @click="kembaliKeDashboard">Tutup</button>
          </div>
        </template>
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
import { useAuthStore } from '@/stores/auth.js'
import { useProgressStore } from '@/stores/progress.js'

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
const auth = useAuthStore()
const progress = useProgressStore()

const huruf = ['A', 'B', 'C', 'D', 'E']

const tipeSoal = [
  { nama: 'Pilihan Ganda', kelas: 'tipe-green', desc: 'Pilih satu jawaban yang paling tepat dari beberapa opsi.' },
  { nama: 'Pilihan Kompleks', kelas: 'tipe-orange', desc: 'Pilih semua jawaban yang benar. Bisa lebih dari satu opsi.' },
  { nama: 'Uraian', kelas: 'tipe-red', desc: 'Tulis jawabanmu dengan kalimat sendiri, jelaskan langkah dan alasannya.' },
]

/* ---------- Data soal ---------- */
const poolKompleks = [
  { teks: 'Manakah struktur data berikut yang mendukung operasi <i>push</i> dan <i>pop</i> dalam O(1)?', opsi: ['Stack', 'Queue (array biasa)', 'Linked List (di head)', 'Binary Search Tree'] },
  { teks: 'Manakah algoritma pengurutan berikut yang stabil?', opsi: ['Merge Sort', 'Quick Sort', 'Insertion Sort', 'Heap Sort'] },
]
const poolGanda = [
  { teks: 'Diberikan sebuah array bilangan bulat. Algoritma apa yang paling tepat untuk mencari subarray dengan jumlah<br>maksimum dalam waktu O(n)?', opsi: ["Kadane's Algorithm", 'Brute Force O(n²)', 'Binary Search', 'Depth First Search'] },
  { teks: 'Kompleksitas waktu terburuk dari Binary Search pada array terurut berukuran n adalah ...', opsi: ['O(n)', 'O(log n)', 'O(n log n)', 'O(1)'] },
  { teks: 'Algoritma yang digunakan untuk mencari jalur terpendek pada graf berbobot non-negatif adalah ...', opsi: ['Dijkstra', 'DFS', 'Kruskal', 'Topological Sort'] },
]
const poolUraian = [
  { teks: 'Jelaskan bagaimana cara kerja algoritma Sieve of Eratosthenes dan berapa kompleksitas waktunya.' },
  { teks: 'Jelaskan perbedaan pendekatan <i>greedy</i> dan <i>dynamic programming</i> beserta contoh masing-masing.' },
]

const soal = reactive(
  Array.from({ length: 20 }, (_, i) => {
    const no = i + 1
    if (no <= 10) {
      const p = poolGanda[(no - 1) % poolGanda.length]
      return { tipe: 'ganda', bobot: 2, ...p }
    }
    if (no <= 15) {
      const p = poolKompleks[(no - 11) % poolKompleks.length]
      return { tipe: 'kompleks', bobot: 3, ...p }
    }
    const p = poolUraian[(no - 16) % poolUraian.length]
    return { tipe: 'uraian', bobot: 5, ...p }
  })
)

/* ---------- State ---------- */
const aktif = ref(0)
const petunjukTerbuka = ref(true)
const menuUser = ref(false)

const jawaban = reactive({})
const ragu = reactive({})

const total = soal.length
const soalAktif = computed(() => soal[aktif.value])

/* ---------- Helper ---------- */
function sudahDijawab(i) {
  const j = jawaban[i]
  if (j === undefined || j === null) return false
  if (Array.isArray(j)) return j.length > 0
  if (typeof j === 'string') return j.trim().length > 0
  return true
}

const jmlTerjawab = computed(() => soal.filter((_, i) => sudahDijawab(i)).length)
const jmlRagu = computed(() => soal.filter((_, i) => ragu[i]).length)
const jmlBelum = computed(() => total - jmlTerjawab.value - soal.filter((_, i) => ragu[i] && !sudahDijawab(i)).length)

function kelasNomer(i) {
  return {
    'n-open': i === aktif.value,
    'n-ragu': i !== aktif.value && ragu[i],
    'n-done': i !== aktif.value && !ragu[i] && sudahDijawab(i),
    'n-empty': i !== aktif.value && !ragu[i] && !sudahDijawab(i),
  }
}

const labelTipe = computed(() => {
  if (soalAktif.value.tipe === 'ganda') return 'Pilihan Ganda · pilih 1'
  if (soalAktif.value.tipe === 'kompleks') return 'Pilihan Kompleks · pilih semua yang benar'
  return 'Uraian'
})
const petunjukTipe = computed(() => {
  if (soalAktif.value.tipe === 'ganda') return 'Pilih satu jawaban yang paling tepat.'
  if (soalAktif.value.tipe === 'kompleks') return 'Pilih semua jawaban yang benar.'
  return 'Tulis jawabanmu beserta langkah dan alasannya.'
})

/* ---------- Selesai ---------- */
const modalSelesai = ref(false)
const dikumpulkan = ref(false)
// "Selesai" tampil di soal terakhir, atau di semua soal bila prop tampilkanSelesai = true
const tombolSelesai = computed(() => props.tampilkanSelesai || aktif.value === total - 1)
function kumpulkan() {
  if (dikumpulkan.value) return
  dikumpulkan.value = true
  clearInterval(timer)
  progress.markPreTestCompleted()
}
function kembaliKeDashboard() {
  router.replace({ name: 'siswa.dashboard' })
}

/* ---------- Aksi ---------- */
function pilihGanda(i) {
  if (dikumpulkan.value) return
  jawaban[aktif.value] = i
}
function pilihKompleks(i) {
  if (dikumpulkan.value) return
  const cur = Array.isArray(jawaban[aktif.value]) ? [...jawaban[aktif.value]] : []
  const idx = cur.indexOf(i)
  idx === -1 ? cur.push(i) : cur.splice(idx, 1)
  jawaban[aktif.value] = cur
}
function isiUraian(v) {
  if (dikumpulkan.value) return
  jawaban[aktif.value] = v
}
function toggleRagu() {
  if (dikumpulkan.value) return
  ragu[aktif.value] = !ragu[aktif.value]
}
function pindah(i) {
  if (i < 0 || i >= total) return
  aktif.value = i
}

/* ---------- Timer (15 menit) ---------- */
const sisaDetik = ref(15 * 60)
let timer = null
const waktuTampil = computed(() => {
  const m = Math.floor(sisaDetik.value / 60)
  const s = sisaDetik.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})
onMounted(() => {
  timer = setInterval(() => {
    if (sisaDetik.value > 0) {
      sisaDetik.value--
      if (sisaDetik.value === 0) {
        modalSelesai.value = true
        kumpulkan()
      }
    }
  }, 1000)
})
onBeforeUnmount(() => clearInterval(timer))

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
.tipe-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
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

/* Responsif */
@media (max-width: 1000px) {
  .main { grid-template-columns: 1fr; padding: 20px; }
  .header { padding: 0 20px; }
  .tipe-grid { grid-template-columns: 1fr; }
  .timer-label { display: none; }
}
</style>
