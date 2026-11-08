<script setup>
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { anggotaService } from '@/services/anggota.js'

const auth = useAuthStore()
const ringkasan = ref(null)
const memuat = ref(true)
const galat = ref(false)

async function muat() {
  memuat.value = true
  galat.value = false
  try {
    ringkasan.value = await anggotaService.ringkasan()
  } catch {
    galat.value = true
  } finally {
    memuat.value = false
  }
}

onMounted(muat)
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] px-4 py-6 text-[#16233a] sm:px-7 sm:py-8">
    <main class="mx-auto max-w-5xl space-y-6">
      <header>
        <h1 class="text-2xl font-bold">Dashboard Anggota</h1>
        <p class="mt-2 text-sm text-[#5e6b7c]">Halo, {{ auth.nama }}.</p>
      </header>

      <p v-if="memuat" role="status" class="rounded-xl border border-[#e0e7f2] bg-white p-5 text-sm text-[#5e6b7c]">
        Memuat ringkasan…
      </p>
      <div v-else-if="galat" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e0e7f2] bg-white p-5">
        <p role="alert" class="text-sm text-[#5e6b7c]">Ringkasan belum bisa dimuat.</p>
        <button type="button" class="min-h-[44px] cursor-pointer rounded-lg bg-[#0a1f3f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#173963] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" @click="muat">
          Coba lagi
        </button>
      </div>
      <template v-else-if="ringkasan">
        <section aria-label="Ringkasan anggota" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
            <h2 class="text-sm font-semibold text-[#5e6b7c]">Referal Anda</h2>
            <p class="mt-3 text-3xl font-bold text-[#0a1f3f]">{{ ringkasan.jumlahReferal }}</p>
          </article>
          <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
            <h2 class="text-sm font-semibold text-[#5e6b7c]">Peringkat Anda</h2>
            <p class="mt-3 text-3xl font-bold text-[#0a1f3f]">
              {{ ringkasan.jumlahReferal > 0 ? `${ringkasan.peringkat} dari ${ringkasan.jumlahAnggota}` : '-' }}
            </p>
            <p class="mt-2 text-xs text-[#5e6b7c]">{{ ringkasan.jumlahReferal === 0 ? 'Belum ada referal' : 'Di antara semua anggota' }}</p>
          </article>
          <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
            <h2 class="text-sm font-semibold text-[#5e6b7c]">Total referal semua anggota</h2>
            <p class="mt-3 text-3xl font-bold text-[#0a1f3f]">{{ ringkasan.totalReferal }}</p>
          </article>
        </section>
        <p v-if="ringkasan.jumlahReferal === 0" class="rounded-xl border border-[#e0e7f2] bg-white p-5 text-sm leading-relaxed text-[#4b5c75]">
          Belum ada pendaftar lewat kode Anda. Bagikan kode dari menu Kode Referal.
        </p>
      </template>
    </main>
  </div>
</template>
