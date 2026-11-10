<script setup>
// A6 · Log aktivitas user (khusus Super Admin).
// Ditampilkan sebagai tabel ringkas: Nama User · Kegiatan User · Waktu.
// Event akun/admin dari log_aktivitas digabung per user + aksi + hari (badge ×N),
// aktivitas belajar dibaca dari tabel domain dan tampil per objek.
import { computed, onMounted, ref, watch } from 'vue'
import Select from 'primevue/select'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import { adminService } from '@/services/admin.js'
import { pesanError } from '@/lib/errors.js'

const SEMUA = 'semua'

const siswaList = ref([])
const siswaId = ref(SEMUA)
const dari = ref('')
const sampai = ref('')
const kategori = ref(SEMUA)

const memuatSiswa = ref(false)
const memuat = ref(false)
const galat = ref('')

const timeline = ref([])
const detail = ref({})
const membuka = ref('')

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

// Ratakan timeline per tanggal menjadi baris tabel.
const baris = computed(() =>
  timeline.value.flatMap((hari) =>
    hari.entri.map((entri) => ({ ...entri, tanggal: hari.tanggal })),
  ),
)

onMounted(async () => {
  memuatSiswa.value = true
  try {
    const hasil = await adminService.daftarSiswa({ perPage: 100 })
    siswaList.value = hasil.items
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat daftar siswa.')
  } finally {
    memuatSiswa.value = false
  }

  await muat()
})

watch([siswaId, dari, sampai, kategori], () => {
  void muat()
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
    day: 'numeric', month: 'short', year: 'numeric',
  }).format(new Date(`${tanggal}T00:00:00`))
}

function jam(iso) {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit', minute: '2-digit',
  }).format(new Date(iso))
}

function labelKategori(k) {
  return { akun: 'Akun', belajar: 'Belajar', admin: 'Admin' }[k] ?? k
}

function warnaKategori(k) {
  return {
    akun: 'bg-[#e8f0fb] text-[#0759a5]',
    belajar: 'bg-[#e9f7ee] text-[#1d7a42]',
    admin: 'bg-[#fdf3dd] text-[#8a6100]',
  }[k] ?? 'bg-[#eef1f6] text-[#56657d]'
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
    judul="Log Aktivitas"
    deskripsi="Riwayat aktivitas user selama 90 hari terakhir. Data diperbarui otomatis; tidak dapat diubah."
    :galat="galat"
    @coba-lagi="muat"
  />

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
      <input v-model="dari" type="date" class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]">
    </label>

    <label class="flex flex-col gap-1 text-[12px] font-semibold text-[#3d4b63]">
      Sampai
      <input v-model="sampai" type="date" class="h-[38px] rounded-lg border border-[#cfd9e6] px-3 text-[13px] text-[#172033]">
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
