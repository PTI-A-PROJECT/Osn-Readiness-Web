<script setup>
// A2 · Kelola siswa. Daftar berpaginasi, ubah nama/email/tingkat aktif,
// nonaktifkan, dan hapus. 422 per kolom dibaca lewat pesanField.
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import AdminPaginasi from '@/components/admin/AdminPaginasi.vue'
import AdminKonfirmasi from '@/components/admin/AdminKonfirmasi.vue'
import { MODUL, useAdminStore } from '@/stores/admin.js'
import { adminService } from '@/services/admin.js'
import { pesanError, pesanField } from '@/lib/errors.js'

const admin = useAdminStore()
const toast = useToast()

const MOD = MODUL.SISWA
const galat = ref('')
const formGalat = ref('')
const tingkatList = ref([])

const daftar = computed(() => admin.daftarModul(MOD))
const pencarian = ref('')
const filterTingkat = ref('semua')
const filterStatus = ref('semua')
const daftarTersaring = computed(() => daftar.value.filter((s) => {
  const kata = pencarian.value.trim().toLowerCase()
  const cocokTeks = !kata || [s.nama, s.email].some((value) => String(value ?? '').toLowerCase().includes(kata))
  const cocokTingkat = filterTingkat.value === 'semua' || String(s.tingkatAktifId) === filterTingkat.value
  const cocokStatus = filterStatus.value === 'semua' || (filterStatus.value === 'aktif' ? s.aktif : !s.aktif)
  return cocokTeks && cocokTingkat && cocokStatus
}))
const meta = computed(() => admin.metaModul(MOD))
function eksporCSV() {
  const baris = [['Nama', 'Email', 'Tingkat', 'Status'],
    ...daftarTersaring.value.map((s) => [
      s.nama,
      s.email,
      tingkatList.value.find((t) => t.id === s.tingkatAktifId)?.nama ?? '',
      s.aktif ? 'Aktif' : 'Nonaktif',
    ]),
  ]
  const csv = '\uFEFF' + baris.map((kolom) => kolom.map((v) =>
    '"' + String(v ?? '').replaceAll('"', '""') + '"',
  ).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'siswa-halaman-ini.csv'
  link.click()
  URL.revokeObjectURL(url)
}

const modalUbah = ref(false)
const form = reactive({ id: null, nama: '', email: '', tingkatAktifId: null, aktif: true })
const konfirmasi = reactive({ terbuka: false, mode: null, target: null })

const pilihanTingkat = computed(() => [
  { label: 'Belum ada tingkat aktif', value: null },
  ...tingkatList.value.map((t) => ({ label: t.nama, value: t.id })),
])

async function muat({ force = false } = {}) {
  galat.value = ''
  try {
    await admin.muatDaftar(MOD, { force })
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat daftar siswa.')
  }
}

async function bukaUbah(s) {
  formGalat.value = ''
  Object.assign(form, {
    id: s.id, nama: s.nama, email: s.email, tingkatAktifId: s.tingkatAktifId, aktif: s.aktif,
  })
  modalUbah.value = true
}

async function simpan() {
  formGalat.value = ''
  try {
    await admin.jalankan({
      aksi: () =>
        adminService.ubahSiswa({
          id: form.id,
          payload: {
            name: form.nama,
            email: form.email,
            tingkat_aktif_id: form.tingkatAktifId,
          },
        }),
      muatUlang: () => muat({ force: true }),
    })
    modalUbah.value = false
    toast.add({ severity: 'success', summary: 'Data siswa diperbarui', life: 4000 })
  } catch (err) {
    // Pesan per kolom didahulukan supaya user tahu kolom mana yang salah.
    const perKolom = pesanField(err, 'name') ?? pesanField(err, 'email') ?? pesanField(err, 'tingkat_aktif_id')
    formGalat.value = perKolom ?? pesanError(err, 'Gagal menyimpan perubahan.')
  }
}

function ask(mode, s) {
  konfirmasi.terbuka = true
  konfirmasi.mode = mode
  konfirmasi.target = s
}

async function jalankanKonfirmasi() {
  const s = konfirmasi.target
  if (konfirmasi.mode === 'nonaktifkan') {
    await admin.jalankan({
      aksi: () => adminService.nonaktifkanSiswa({ id: s.id }),
      muatUlang: () => muat({ force: true }),
    })
    toast.add({ severity: 'success', summary: 'Siswa dinonaktifkan', life: 4000 })
    return
  }
  await admin.jalankan({
    aksi: () => adminService.hapusSiswa({ id: s.id }),
    muatUlang: () => muat({ force: true }),
  })
  toast.add({ severity: 'success', summary: 'Siswa dihapus', life: 4000 })
}

onMounted(async () => {
  tingkatList.value = await adminService.daftarTingkatAdmin().catch(() => [])
  await muat({ force: true })
})
</script>

<template>
  <div>
    <AdminPageHeader
      judul="Manajemen Siswa"
      deskripsi="Kelola data, status, dan progres seluruh siswa terdaftar."
      :memuat="admin.loading"
      :galat="galat"
      @coba-lagi="muat({ force: true })"
    >
      <template #aksi-extra>
        <Button label="Muat ulang" severity="secondary" outlined :loading="admin.loading" @click="muat({ force: true })" />
      </template>
    </AdminPageHeader>

    <Card>
      <template #content>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-[14px] font-bold">Daftar Siswa</h2>
          <button type="button" class="rounded-lg border border-[#dce4f0] bg-white px-3 py-2 text-[11px] font-semibold text-[#193961]" @click="eksporCSV">
            Ekspor CSV (halaman ini)
          </button>
        </div>
        <div class="mb-4 flex flex-wrap gap-2">
          <input v-model="pencarian" type="search" placeholder="Cari nama atau email..." class="min-w-[220px] flex-1 rounded-lg border border-[#dce4f0] bg-[#f5f8fc] px-3 py-2 text-[12px]" />
          <select v-model="filterTingkat" class="rounded-lg border border-[#dce4f0] bg-white px-3 py-2 text-[12px]">
            <option value="semua">Tingkat: Semua</option>
            <option v-for="t in tingkatList" :key="t.id" :value="String(t.id)">{{ t.nama }}</option>
          </select>
          <select v-model="filterStatus" class="rounded-lg border border-[#dce4f0] bg-white px-3 py-2 text-[12px]">
            <option value="semua">Status: Semua</option>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
        <p v-if="admin.loading && !daftar.length" class="text-sm text-[#6b778c]">Memuat siswa…</p>
        <p v-else-if="!daftar.length" class="py-6 text-center text-sm font-bold">Belum ada siswa.</p>

        <p v-else-if="!daftarTersaring.length" class="py-6 text-center text-[12px] text-[#71809a]">Tidak ada siswa sesuai filter pada halaman ini.</p>
        <div v-else class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-[13px]">
          <thead>
            <tr class="border-b border-[#eef1f6] text-[12px] uppercase text-[#6b778c]">
              <th class="py-2">Nama</th>
              <th class="py-2">Email</th>
              <th class="py-2">Tingkat Aktif</th>
              <th class="py-2">Status</th>
              <th class="py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in daftarTersaring" :key="s.id" class="border-b border-[#f5f7fa]">
              <td class="py-2.5 font-medium">{{ s.nama || '—' }}</td>
              <td class="py-2.5 text-[#4a5568]">{{ s.email }}</td>
              <td class="py-2.5 text-[#4a5568]">
                {{ tingkatList.find((t) => t.id === s.tingkatAktifId)?.nama ?? '—' }}
              </td>
              <td class="py-2.5">
                <span
                  class="rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                  :class="s.aktif ? 'bg-[#f3fcf6] text-[#15803d]' : 'bg-[#fdf0f0] text-[#a33333]'"
                >
                  {{ s.aktif ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="py-2.5">
                <div class="flex justify-end gap-1.5">
                  <Button size="small" severity="secondary" outlined label="Ubah" @click="bukaUbah(s)" />
                  <Button
                    v-if="s.aktif"
                    size="small"
                    severity="warn"
                    outlined
                    label="Nonaktifkan"
                    @click="ask('nonaktifkan', s)"
                  />
                  <Button size="small" severity="danger" outlined label="Hapus" @click="ask('hapus', s)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table></div>

        <AdminPaginasi :meta="meta" :memuat="admin.loading" @ganti="(h) => admin.keHalaman(MOD, h)" />
      </template>
    </Card>

    <!-- Modal ubah -->
    <Dialog v-model:visible="modalUbah" modal header="Ubah Data Siswa" :style="{ width: '30rem' }">
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-[13px] font-medium" for="f-nama">Nama</label>
          <InputText id="f-nama" v-model="form.nama" class="w-full" />
        </div>
        <div>
          <label class="mb-1 block text-[13px] font-medium" for="f-email">Email</label>
          <InputText id="f-email" v-model="form.email" class="w-full" />
        </div>
        <div>
          <label class="mb-1 block text-[13px] font-medium" for="f-tingkat">Tingkat Aktif</label>
          <Select
            id="f-tingkat"
            v-model="form.tingkatAktifId"
            :options="pilihanTingkat"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <p v-if="formGalat" class="rounded-xl bg-[#fdf0f0] px-3 py-2 text-[13px] text-[#a33333]">
          {{ formGalat }}
        </p>
      </div>
      <template #footer>
        <Button label="Batal" severity="secondary" text @click="modalUbah = false" />
        <Button label="Simpan" :loading="admin.menyimpan" @click="simpan" />
      </template>
    </Dialog>

    <!-- Konfirmasi -->
    <AdminKonfirmasi
      :terbuka="konfirmasi.terbuka"
      :judul="konfirmasi.mode === 'hapus' ? 'Hapus siswa?' : 'Nonaktifkan siswa?'"
      :pesan="
        konfirmasi.mode === 'hapus'
          ? `${konfirmasi.target?.nama} akan dihapus permanen dari daftar. Riwayat hasilnya ikut terhapus.`
          : `${konfirmasi.target?.nama} tidak bisa login sampai diaktifkan kembali.`
      "
      :label-konfirmasi="konfirmasi.mode === 'hapus' ? 'Hapus' : 'Nonaktifkan'"
      :sibuk="admin.menyimpan"
      :aksi="jalankanKonfirmasi"
      @tutup="konfirmasi.terbuka = false"
    />
  </div>
</template>
