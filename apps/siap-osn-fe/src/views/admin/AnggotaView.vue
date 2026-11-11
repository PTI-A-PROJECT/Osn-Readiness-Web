<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import AdminPaginasi from '@/components/admin/AdminPaginasi.vue'
import { MODUL, useAdminStore } from '@/stores/admin.js'
import { pesanError } from '@/lib/errors.js'

const admin = useAdminStore()
const MOD = MODUL.ANGGOTA
const galat = ref('')
const daftar = computed(() => admin.daftarModul(MOD))
const meta = computed(() => admin.metaModul(MOD))

// Peringkat dihitung dari halaman yang sedang dimuat, bukan seluruh anggota.
const filterReferal = ref('semua')
const jumlahPositif = computed(() => daftar.value.map((a) => a.jumlahUndang).filter((n) => n > 0))
const jumlahTerbanyak = computed(() => (jumlahPositif.value.length ? Math.max(...jumlahPositif.value) : null))
const jumlahTersedikit = computed(() => (jumlahPositif.value.length ? Math.min(...jumlahPositif.value) : null))
const daftarTersaring = computed(() => daftar.value.filter((a) => {
  if (filterReferal.value === 'terbanyak') return a.jumlahUndang === jumlahTerbanyak.value
  if (filterReferal.value === 'tersedikit') return a.jumlahUndang === jumlahTersedikit.value
  if (filterReferal.value === 'tanpa') return a.jumlahUndang === 0
  return true
}))

async function muat({ force = false } = {}) {
  galat.value = ''
  try {
    await admin.muatDaftar(MOD, { force })
  } catch (err) {
    galat.value = pesanError(err, 'Gagal memuat daftar anggota.')
  }
}

onMounted(() => muat({ force: true }))
</script>

<template>
  <div>
    <AdminPageHeader
      judul="Pantau Anggota"
      deskripsi="Pantau jumlah pendaftar yang masuk lewat kode referal tiap anggota."
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
          <h2 class="text-[14px] font-bold">Daftar Anggota</h2>
          <select v-model="filterReferal" class="rounded-lg border border-[#dce4f0] bg-white px-3 py-2 text-[12px]">
            <option value="semua">Referal: Semua</option>
            <option value="terbanyak">Referal terbanyak (halaman ini)</option>
            <option value="tersedikit">Referal tersedikit (halaman ini)</option>
            <option value="tanpa">Belum ada referal</option>
          </select>
        </div>
        <p v-if="admin.loading && !daftar.length" class="text-sm text-[#6b778c]">Memuat anggota…</p>
        <p v-else-if="!daftar.length" class="py-6 text-center text-sm font-bold">Belum ada anggota. Impor daftar anggota dengan perintah anggota:impor.</p>
        <p v-else-if="!daftarTersaring.length" class="py-6 text-center text-[12px] text-[#71809a]">Tidak ada anggota sesuai filter pada halaman ini.</p>
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[560px] text-left text-[13px]">
            <thead>
              <tr class="border-b border-[#eef1f6] text-[12px] uppercase text-[#6b778c]">
                <th scope="col" class="py-2">NIM</th>
                <th scope="col" class="py-2">Nama</th>
                <th scope="col" class="py-2">Kode Referal</th>
                <th scope="col" class="py-2 text-right">Jumlah Referal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in daftarTersaring" :key="a.id" class="border-b border-[#f5f7fa]">
                <td class="py-2.5">{{ a.nim }}</td>
                <td class="py-2.5 font-medium">{{ a.nama }}</td>
                <td class="py-2.5 text-[#4a5568]">{{ a.kodeReferal || '-' }}</td>
                <td class="py-2.5 text-right tabular-nums">{{ a.jumlahUndang }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <AdminPaginasi :meta="meta" :memuat="admin.loading" @ganti="(h) => admin.keHalaman(MOD, h)" />
      </template>
    </Card>
  </div>
</template>
