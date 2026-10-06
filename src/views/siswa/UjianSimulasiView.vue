<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'
import { usePretestStore } from '@/stores/pretest.js'
import { useProgressStore } from '@/stores/progress.js'
import { STATUS_SIMULASI, useSimulasiStore, WAKTU_HABIS } from '@/stores/simulasi.js'
import { pesanError } from '@/lib/errors.js'

// Halaman ujian simulasi. Kerangka soal + autosave mengikuti LatihanView.vue;
// yang berbeda: timer dihitung dari `batas_pada` server (reload tidak menambah
// waktu) dan setelah selesai selalu pindah ke halaman hasil.

const route = useRoute()
const router = useRouter()
const toast = useToast()
const simulasi = useSimulasiStore()
const pretest = usePretestStore()
const progress = useProgressStore()

const memuatAwal = ref(true)
const galatAwal = ref('')
const aktif = ref(0)
const jawaban = reactive({})
const ragu = reactive({})
const modalSelesai = ref(false)
const mengirim = ref(false)

const bolehMengerjakan = computed(() => simulasi.status === STATUS_SIMULASI.MENGERJAKAN)
const soal = computed(() => simulasi.soal)
const total = computed(() => soal.value.length)
const soalAktif = computed(() => soal.value[aktif.value] ?? null)

function seedJawaban() {
  for (const k of Object.keys(jawaban)) delete jawaban[k]
  for (const k of Object.keys(ragu)) delete ragu[k]
  soal.value.forEach((s, i) => {
    jawaban[i] = s.jawaban ?? null
  })
  aktif.value = 0
}

function sudahDijawab(i) {
  const j = jawaban[i]
  return !(j === undefined || j === null || (typeof j === 'string' && !j.trim()))
}
const jmlTerjawab = computed(() => soal.value.filter((_, i) => sudahDijawab(i)).length)

/* ---------- Navigasi ---------- */
function pindah(i) {
  if (i < 0 || i >= total.value) return
  aktif.value = i
}
function toggleRagu() {
  if (!bolehMengerjakan.value) return
  ragu[aktif.value] = !ragu[aktif.value]
}

/* ---------- Timer dari server ---------- */
// Batas waktu dihitung dari `batas_pada`, bukan dari hitungan lokal, sehingga
// reload di tengah ujian melanjutkan dari sisa waktu yang sama di server.
const sisaDetik = ref(0)
const waktuTampil = computed(() => {
  const m = Math.floor(sisaDetik.value / 60)
  const s = sisaDetik.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

let timerJam = null
function hitungSisa() {
  if (!simulasi.batasPada) return
  const batas = Date.parse(simulasi.batasPada)
  if (Number.isNaN(batas)) return
  sisaDetik.value = Math.max(0, Math.round((batas - Date.now()) / 1000))
  // Waktu habis -> kumpulkan otomatis (server juga menolak simpan jawaban
  // lewat kode WAKTU_HABIS).
  if (sisaDetik.value === 0 && bolehMengerjakan.value) kumpulkan()
}

/* ---------- Autosave (debounce 800 ms per soal) ---------- */
const timerSimpan = {}
function jadwalSimpan(i) {
  clearTimeout(timerSimpan[i])
  timerSimpan[i] = setTimeout(async () => {
    const target = soal.value[i]
    if (!target || !bolehMengerjakan.value) return
    const nilai = jawaban[i]
    const status = await simulasi.simpanJawaban({
      soalId: target.id,
      jawaban: nilai === '' ? null : (nilai ?? null),
    })
    // Server sudah menutup percobaan: langsung kumpulkan, jangan diamkan.
    if (status === WAKTU_HABIS) kumpulkan()
  }, 800)
}
function pilihGanda(kode) {
  if (!bolehMengerjakan.value) return
  jawaban[aktif.value] = kode
  jadwalSimpan(aktif.value)
}
function isiIsian(v) {
  if (!bolehMengerjakan.value) return
  jawaban[aktif.value] = v
  jadwalSimpan(aktif.value)
}

/* ---------- Kumpulkan ---------- */
let timerPolling = null
function stopPolling() {
  clearInterval(timerPolling)
  timerPolling = null
}
function mulaiPolling() {
  stopPolling()
  timerPolling = setInterval(async () => {
    const hasil = await simulasi.cekHasil()
    if (!hasil) return
    stopPolling()
    await setelahSelesai()
  }, 5000)
}

// Tahap bisa berubah jadi LULUS / PUTARAN_HABIS setelah hasil keluar, dan
// tingkat berikutnya ikut terbuka — jadi kedua sumber data disegarkan.
async function setelahSelesai() {
  await progress.fetchDashboard({ force: true }).catch(() => {})
  await pretest.muatTingkat({ force: true }).catch(() => {})
  router.replace({ name: 'siswa.simulasi.hasil', params: { hasilId: simulasi.hasilId } })
}

async function kumpulkan() {
  if (mengirim.value || !bolehMengerjakan.value) return
  mengirim.value = true
  try {
    const hasil = await simulasi.kumpulkan()
    modalSelesai.value = false
    if (!hasil) {
      // 503 HASIL_SEDANG_DIPROSES: jawaban terkunci, tunggu nilai.
      mulaiPolling()
      return
    }
    await setelahSelesai()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal mengumpulkan', detail: pesanError(err), life: 4000 })
    modalSelesai.value = false
  } finally {
    mengirim.value = false
  }
}

function kembaliKeLobi() {
  router.push({ name: 'siswa.simulasi' })
}

onMounted(async () => {
  const simulasiId = Number(route.params.simulasiId)
  if (!Number.isFinite(simulasiId) || simulasiId <= 0) {
    galatAwal.value = 'ID simulasi tidak valid. Buka simulasi dari daftar.'
    memuatAwal.value = false
    return
  }
  try {
    // Idempoten: 201 untuk percobaan baru, 200 untuk yang sedang berjalan,
    // jadi reload di tengah ujian menyambung percobaan yang sama.
    const status = await simulasi.mulai({ simulasiId })
    if (status === STATUS_SIMULASI.SELESAI) {
      router.replace({ name: 'siswa.simulasi.hasil', params: { hasilId: simulasi.hasilId } })
      return
    }
    if (status === STATUS_SIMULASI.MENILAI) {
      mulaiPolling()
      memuatAwal.value = false
      return
    }
    seedJawaban()
    hitungSisa()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal membuka simulasi', detail: pesanError(err), life: 4000 })
    galatAwal.value = pesanError(err, 'Tidak dapat membuka simulasi. Coba lagi.')
    memuatAwal.value = false
    return
  }
  memuatAwal.value = false
  timerJam = setInterval(hitungSisa, 1000)
})

onBeforeUnmount(() => {
  clearInterval(timerJam)
  stopPolling()
  for (const k of Object.keys(timerSimpan)) clearTimeout(timerSimpan[k])
})
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] text-[#0f1b33]">
    <header class="flex items-center justify-between border-b border-[#e6ebf2] bg-white px-7 py-4">
      <div>
        <h1 class="text-[17px] font-bold leading-tight">Simulasi Seleksi</h1>
        <p class="mt-0.5 text-[13px] text-[#6b778c]">
          Soal {{ total ? `${aktif + 1} dari ${total}` : '' }}
        </p>
      </div>
      <div class="flex items-center gap-4">
        <span
          class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-[13px] font-semibold"
          :class="sisaDetik <= 300 ? 'bg-[#fde4e4] text-[#b91c1c]' : 'bg-[#eef1f6] text-[#2a3a52]'"
        >
          Sisa Waktu: {{ waktuTampil }}
        </span>
        <span v-if="simulasi.simpanError" class="text-xs text-[#b45309]">
          Gagal menyimpan, periksa koneksi
        </span>
        <UserMenu />
      </div>
    </header>

    <main class="space-y-5 px-6 py-6">
      <!-- STATUS AWAL -->
      <section
        v-if="memuatAwal"
        class="rounded-2xl border border-[#e6ebf2] bg-white px-5 py-3 text-[13px] text-[#6b778c]"
      >
        Menyiapkan simulasi…
      </section>
      <section
        v-else-if="galatAwal"
        class="space-y-3 rounded-2xl border border-[#f3c2c2] bg-[#fdf0f0] px-5 py-3 text-[13px] text-[#a33333]"
      >
        <p>{{ galatAwal }}</p>
        <button
          type="button"
          class="rounded-full border border-[#a33333] px-4 py-1.5 font-semibold"
          @click="kembaliKeLobi"
        >
          Kembali ke Daftar Simulasi
        </button>
      </section>

      <!-- SEDANG DINILAI -->
      <section
        v-else-if="simulasi.status === STATUS_SIMULASI.MENILAI"
        class="rounded-2xl border border-[#f3d9a8] bg-[#fffaef] px-5 py-3 text-[13px] text-[#8a5a12]"
      >
        Jawabanmu sudah dikumpulkan dan sedang dinilai. Halaman ini akan menampilkan hasil
        otomatis begitu selesai.
      </section>

      <!-- PENGERJAAN -->
      <template v-else-if="bolehMengerjakan && soalAktif">
        <section class="rounded-3xl border border-[#e6ebf2] bg-white p-6">
          <div class="flex items-center justify-between">
            <span class="rounded-md bg-[#e8f0fe] px-2.5 py-1 text-xs font-semibold text-[#1d4ed8]">
              Soal {{ aktif + 1 }} dari {{ total }}
            </span>
            <span class="text-xs text-[#6b778c]">Bobot {{ soalAktif.bobot }} poin</span>
          </div>
          <div v-if="soalAktif.konteks" class="mt-4 rounded-xl bg-[#f6f9ff] p-4 text-sm">
            <strong class="block">{{ soalAktif.konteks.judul }}</strong>
            <p class="mt-1 text-[#374151]">{{ soalAktif.konteks.isi }}</p>
            <img
              v-if="soalAktif.konteks.gambar"
              :src="soalAktif.konteks.gambar"
              alt="Gambar konteks soal"
              class="mt-3 max-w-full rounded-lg"
            />
          </div>
          <img
            v-if="soalAktif.gambar"
            :src="soalAktif.gambar"
            alt="Gambar soal"
            class="mt-4 max-w-full rounded-xl"
          />
          <h3 class="mt-4 text-[15px] font-semibold" v-html="soalAktif.pertanyaan"></h3>

          <div v-if="soalAktif.tipe === 'ganda'" class="mt-4 flex flex-col gap-2">
            <button
              v-for="o in soalAktif.opsi"
              :key="o.kode"
              type="button"
              class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-left text-sm"
              :class="jawaban[aktif] === o.kode ? 'border-[#2563eb] bg-[#f6f9ff]' : 'border-[#e3e8f1]'"
              @click="pilihGanda(o.kode)"
            >
              <b class="text-[#1e3a8a]">{{ o.kode }}.</b>
              <span>{{ o.teks }}</span>
            </button>
          </div>
          <textarea
            v-else
            class="mt-4 w-full rounded-xl border border-[#e3e8f1] p-3 text-sm"
            rows="5"
            placeholder="Tulis jawabanmu di sini..."
            :value="jawaban[aktif] || ''"
            @input="isiIsian($event.target.value)"
          ></textarea>

          <div class="mt-4 flex items-center justify-between border-t border-[#eef1f6] pt-4">
            <button
              type="button"
              class="rounded-lg border border-[#ef6b6b] bg-[#fde4e4] px-4 py-2 text-[13px] font-medium text-[#b91c1c] disabled:opacity-40"
              :disabled="aktif === 0"
              @click="pindah(aktif - 1)"
            >
              ← Sebelumnya
            </button>
            <button
              type="button"
              class="rounded-lg border border-[#f2ab57] bg-[#fff3e0] px-4 py-2 text-[13px] font-medium text-[#c2610c]"
              @click="toggleRagu"
            >
              {{ ragu[aktif] ? '✓ Ragu' : 'Ragu' }}
            </button>
            <button
              v-if="aktif === total - 1"
              type="button"
              class="rounded-lg border border-[#3b6fe0] bg-white px-4 py-2 text-[13px] font-medium text-[#1d4ed8]"
              @click="modalSelesai = true"
            >
              Selesai →
            </button>
            <button
              v-else
              type="button"
              class="rounded-lg border border-[#3b6fe0] bg-white px-4 py-2 text-[13px] font-medium text-[#1d4ed8]"
              @click="pindah(aktif + 1)"
            >
              Berikutnya →
            </button>
          </div>
        </section>

        <section class="rounded-3xl border border-[#e6ebf2] bg-white p-6">
          <h3 class="text-sm font-bold text-[#2a3a52]">
            Nomor Soal ({{ jmlTerjawab }}/{{ total }} terjawab)
          </h3>
          <div class="mt-3 grid grid-cols-5 gap-2">
            <button
              v-for="(_, i) in soal"
              :key="i"
              type="button"
              class="rounded-lg border py-2 text-[13px] font-semibold"
              :class="i === aktif
                ? 'border-[#2563eb] bg-[#eaf1ff] text-[#1d4ed8]'
                : sudahDijawab(i)
                  ? 'border-[#8fe0ae] bg-[#c9f5d9] text-[#15803d]'
                  : 'border-[#eaeef5] text-[#b45309]'"
              @click="pindah(i)"
            >
              {{ String(i + 1).padStart(2, '0') }}
            </button>
          </div>
        </section>
      </template>

      <!-- MODAL SELESAI -->
      <div
        v-if="modalSelesai"
        class="fixed inset-0 z-50 grid place-items-center bg-[#1e2d5a80] p-5"
        @click.self="!mengirim && (modalSelesai = false)"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6">
          <h3 class="text-base font-bold">Kumpulkan simulasi?</h3>
          <p class="mt-1 text-sm text-[#4b5563]">
            Kamu menjawab {{ jmlTerjawab }} dari {{ total }} soal. Jawaban tidak bisa diubah
            setelah dikumpulkan.
          </p>
          <div class="mt-5 flex justify-end gap-2">
            <button
              type="button"
              :disabled="mengirim"
              class="rounded-full bg-[#fbb024] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
              @click="modalSelesai = false"
            >
              Kembali
            </button>
            <button
              type="button"
              :disabled="mengirim"
              class="rounded-full bg-[#1e3a8a] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
              @click="kumpulkan"
            >
              {{ mengirim ? 'Mengumpulkan…' : 'Kumpulkan' }}
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
