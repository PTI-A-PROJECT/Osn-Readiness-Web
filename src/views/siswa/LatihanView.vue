<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'
import { useLatihanStore, STATUS_LATIHAN } from '@/stores/latihan.js'
import { useMateriStore } from '@/stores/materi.js'
import { pesanError } from '@/lib/errors.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const latihan = useLatihanStore()
const materi = useMateriStore()

const memuatAwal = ref(true)
const galatAwal = ref('')
const aktif = ref(0)
const jawaban = reactive({})
const ragu = reactive({})
const modalSelesai = ref(false)
const mengirim = ref(false)

const bolehMengerjakan = computed(() => latihan.status === STATUS_LATIHAN.MENGERJAKAN)
const soal = computed(() => latihan.soal)
const total = computed(() => soal.value.length)
const soalAktif = computed(() => soal.value[aktif.value] ?? null)
const hasil = computed(() => latihan.hasil)

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

/* ---------- Polling nilai (status assessing) ---------- */
let timerPolling = null
function stopPolling() {
  clearInterval(timerPolling)
  timerPolling = null
}
// Nilai terbaik di halaman materi harus ikut baru.
async function hasilMuncul() {
  stopPolling()
  if (!materi.tingkatId) return
  await materi.fetchDaftar({ tingkatId: materi.tingkatId, force: true }).catch(() => {})
}

onMounted(async () => {
  const quizId = Number(route.params.quizId)
  if (!Number.isFinite(quizId) || quizId <= 0) {
    galatAwal.value = 'ID quiz tidak valid. Buka latihan dari halaman materi.'
    memuatAwal.value = false
    return
  }
  // Store hangat hanya dipakai bila memang quiz yang sama.
  const hangat =
    latihan.quizId === quizId &&
    latihan.status === STATUS_LATIHAN.MENGERJAKAN &&
    latihan.soal.length
  if (!hangat) {
    try {
      await latihan.mulai({ quizId })
    } catch (err) {
      galatAwal.value = pesanError(err, 'Tidak dapat membuka latihan. Coba lagi.')
      memuatAwal.value = false
      return
    }
  }
  if (latihan.status === STATUS_LATIHAN.SELESAI) {
    await hasilMuncul()
    memuatAwal.value = false
    return
  }
  if (latihan.status === STATUS_LATIHAN.MENILAI) {
    mulaiPolling()
    memuatAwal.value = false
    return
  }
  seedJawaban()
  memuatAwal.value = false
})

const timerSimpan = {}
function jadwalSimpan(i) {
  clearTimeout(timerSimpan[i])
  timerSimpan[i] = setTimeout(() => {
    const target = soal.value[i]
    if (!target || latihan.status !== STATUS_LATIHAN.MENGERJAKAN) return
    const nilai = jawaban[i]
    latihan.simpanJawaban({ soalId: target.id, jawaban: nilai === '' ? null : (nilai ?? null) })
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

function toggleRagu() {
  if (!bolehMengerjakan.value) return
  ragu[aktif.value] = !ragu[aktif.value]
}

function pindah(i) {
  if (i < 0 || i >= total.value) return
  aktif.value = i
}

async function kumpulkan() {
  if (mengirim.value || !bolehMengerjakan.value) return
  mengirim.value = true
  try {
    const hasil = await latihan.kumpulkan()
    modalSelesai.value = false
    if (!hasil) {
      // 503 HASIL_SEDANG_DIPROSES: jawaban terkunci, tunggu nilai.
      mulaiPolling()
      return
    }
    await hasilMuncul()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal mengumpulkan', detail: pesanError(err), life: 4000 })
    modalSelesai.value = false
  } finally {
    mengirim.value = false
  }
}

function mulaiPolling() {
  stopPolling()
  timerPolling = setInterval(async () => {
    const hasil = await latihan.cekHasil()
    if (!hasil) return
    await hasilMuncul()
  }, 5000)
}

function kembaliKeMateri() {
  router.push({ name: 'siswa.materi' })
}

onBeforeUnmount(() => {
  stopPolling()
  for (const k of Object.keys(timerSimpan)) clearTimeout(timerSimpan[k])
})

function formatNilai(nilai) {
  return nilai == null ? '—' : String(Math.round(nilai))
}
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] text-[#0f1b33]">
    <header class="flex items-center justify-between border-b border-[#e6ebf2] bg-white px-7 py-4">
      <div>
        <h1 class="text-[17px] font-bold leading-tight">{{ latihan.materiJudul || 'Latihan' }}</h1>
        <p class="mt-0.5 text-[13px] text-[#6b778c]">Soal {{ total ? `${aktif + 1} dari ${total}` : '' }}</p>
      </div>
      <UserMenu />
    </header>

    <main class="space-y-5 px-6 py-6">
      <!-- STATUS AWAL -->
      <section v-if="memuatAwal" class="rounded-2xl border border-[#e6ebf2] bg-white px-5 py-3 text-[13px] text-[#6b778c]">
        Menyiapkan latihan…
      </section>
      <section v-else-if="galatAwal" class="rounded-2xl border border-[#f3c2c2] bg-[#fdf0f0] px-5 py-3 text-[13px] text-[#a33333]">
        {{ galatAwal }}
      </section>

      <!-- HASIL -->
      <section v-else-if="latihan.status === 'selesai' && hasil" class="rounded-3xl border border-[#e6ebf2] bg-white p-6">
        <h3 class="text-base font-bold">Hasil Latihan</h3>
        <p class="mt-1 text-3xl font-bold">{{ formatNilai(hasil.nilai) }}</p>
        <ul class="mt-5 space-y-2">
          <li
            v-for="j in hasil.jawaban"
            :key="j.soalId"
            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm"
            :class="j.benar ? 'bg-[#f3fcf6]' : 'bg-[#fdf0f0]'"
          >
            <span class="font-semibold">Soal {{ j.urutan }}</span>
            <span class="flex-1 text-xs text-[#6b778c]">{{ j.jawabanUser ?? '—' }}</span>
            <span class="text-xs font-bold" :class="j.benar ? 'text-[#1f8a5b]' : 'text-[#a33333]'">
              {{ j.benar ? 'Benar' : 'Salah' }}
            </span>
          </li>
        </ul>
        <div class="mt-6 flex gap-2">
          <button
            type="button"
            class="rounded-full bg-[#0f2a5c] px-5 py-2 text-[13px] font-semibold text-white"
            @click="kembaliKeMateri"
          >
            Kembali ke Materi
          </button>
        </div>
      </section>

      <!-- SEDANG DINILAI -->
      <section
        v-else-if="latihan.status === STATUS_LATIHAN.MENILAI"
        class="rounded-2xl border border-[#f3d9a8] bg-[#fffaef] px-5 py-3 text-[13px] text-[#8a5a12]"
      >
        Jawabanmu sudah dikumpulkan dan sedang dinilai. Halaman ini akan menampilkan nilai
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
          </div>
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
          <h3 class="text-sm font-bold text-[#2a3a52]">Nomor Soal ({{ jmlTerjawab }}/{{ total }} terjawab)</h3>
          <div class="mt-3 grid grid-cols-5 gap-2">
            <button
              v-for="(_, i) in soal"
              :key="i"
              type="button"
              class="rounded-lg border py-2 text-[13px] font-semibold"
              :class="i === aktif
                ? 'border-[#2563eb] bg-[#eaf1ff] text-[#1d4ed8]'
                : jawaban[i] !== null && jawaban[i] !== undefined && jawaban[i] !== ''
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
      <div v-if="modalSelesai" class="fixed inset-0 z-50 grid place-items-center bg-[#1e2d5a80] p-5" @click.self="!mengirim && (modalSelesai = false)">
        <div class="w-full max-w-md rounded-2xl bg-white p-6">
          <h3 class="text-base font-bold">Kumpulkan latihan?</h3>
          <p class="mt-1 text-sm text-[#4b5563]">Kamu menjawab {{ jmlTerjawab }} dari {{ total }} soal. Jawaban tidak bisa diubah setelah dikumpulkan.</p>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" :disabled="mengirim" class="rounded-full bg-[#fbb024] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="modalSelesai = false">Kembali</button>
            <button type="button" :disabled="mengirim" class="rounded-full bg-[#1e3a8a] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="kumpulkan">{{ mengirim ? 'Mengumpulkan…' : 'Kumpulkan' }}</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
