<script setup>
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { authService } from '@/services/auth.js'
import { anggotaService } from '@/services/anggota.js'
import { pesanError, pesanField } from '@/lib/errors.js'

const auth = useAuthStore()
const kode = ref(auth.user?.kodeReferal ?? '')
const jumlah = ref(auth.user?.jumlahUndang ?? 0)
// Kode anggota berbentuk OSN<NAMA>; bagian nama adalah sisa setelah awalan OSN.
const AWALAN = 'OSN'
const bagianNama = (k) => (String(k).startsWith(AWALAN) ? String(k).slice(AWALAN.length) : '')
const namaInput = ref(bagianNama(kode.value))
const galat = ref('')
const menyimpan = ref(false)
const tersalin = ref(false)
const tersimpan = ref(false)

watch(kode, (k) => { namaInput.value = bagianNama(k) })

onMounted(async () => {
  try {
    const u = await authService.me()
    kode.value = u.kodeReferal ?? ''
    jumlah.value = u.jumlahUndang ?? 0
    namaInput.value = bagianNama(kode.value)
  } catch {
    // Tetap gunakan data user yang telah ada di store.
  }
})

async function simpan() {
  galat.value = ''
  tersimpan.value = false
  menyimpan.value = true
  try {
    const u = await anggotaService.ubahKode(namaInput.value.trim())
    kode.value = u.kodeReferal ?? ''
    jumlah.value = u.jumlahUndang
    auth.user = u
    tersimpan.value = true
  } catch (err) {
    galat.value = pesanField(err, 'nama') ?? pesanError(err, 'Nama kode belum bisa disimpan. Coba lagi.')
  } finally {
    menyimpan.value = false
  }
}

async function salin() {
  if (!kode.value) return
  try {
    await navigator.clipboard.writeText(kode.value)
    tersalin.value = true
    window.setTimeout(() => { tersalin.value = false }, 2000)
  } catch {
    galat.value = 'Kode belum tersalin. Silakan salin manual.'
  }
}
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] px-4 py-6 text-[#16233a] sm:px-7 sm:py-8">
    <main class="mx-auto max-w-3xl space-y-6">
      <header>
        <h1 class="text-2xl font-bold">Kode Referal</h1>
      </header>

      <section class="rounded-2xl border border-[#e0e7f2] bg-white p-5 sm:p-6">
        <label for="kode-referal" class="block text-sm font-semibold">Kode referal Anda</label>
        <div class="mt-2 flex flex-wrap gap-2">
          <input id="kode-referal" :value="kode || 'Belum tersedia'" readonly class="min-h-[44px] min-w-0 flex-1 rounded-lg border border-[#d8e0eb] bg-[#f5f8fc] px-3 py-2.5 font-semibold text-[#0a1f3f]" />
          <button type="button" :disabled="!kode" class="min-h-[44px] cursor-pointer rounded-lg bg-[#0a1f3f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#173963] disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" @click="salin">
            {{ tersalin ? 'Tersalin' : 'Salin' }}
          </button>
        </div>
        <p class="mt-3 text-sm text-[#5e6b7c]">{{ jumlah }} pengguna sudah mendaftar lewat kode Anda</p>
      </section>

      <section class="rounded-2xl border border-[#e0e7f2] bg-white p-5 sm:p-6">
        <h2 class="text-lg font-bold">Ubah nama kode</h2>
        <div class="mt-4">
          <label for="nama-kode" class="block text-sm font-semibold">Nama pada kode</label>
          <input id="nama-kode" v-model="namaInput" maxlength="16" autocomplete="off" class="mt-2 min-h-[44px] w-full rounded-lg border border-[#d8e0eb] bg-white px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" />
          <p class="mt-2 text-xs leading-relaxed text-[#5e6b7c]">Huruf dan angka saja, 2 sampai 16 karakter. Kode menjadi OSN{{ namaInput.toUpperCase() }}.</p>
          <p class="mt-3 rounded-lg bg-[#fff8e8] px-3 py-2.5 text-xs leading-relaxed text-[#735318]">Kode lama berhenti berlaku. Pendaftar yang sudah memakainya tetap terhitung.</p>
        </div>
        <p v-if="galat" role="alert" class="mt-3 text-sm text-[#a33333]">{{ galat }}</p>
        <p v-if="tersimpan" role="status" class="mt-3 text-sm text-[#267244]">Kode diperbarui.</p>
        <button type="button" :disabled="menyimpan || !namaInput.trim() || namaInput.trim() === bagianNama(kode)" class="mt-4 min-h-[44px] cursor-pointer rounded-lg bg-[#0a1f3f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#173963] disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" @click="simpan">
          {{ menyimpan ? 'Menyimpan…' : 'Simpan nama' }}
        </button>
      </section>
    </main>
  </div>
</template>
