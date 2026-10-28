<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardFor } from '@/router/index.js'
import { useAuthStore } from '@/stores/auth.js'

// Ditampilkan sekali setelah login Google akun baru. Bisa diisi atau dilewati;
// keduanya menutup opsi ini untuk selamanya di sisi backend.
const router = useRouter()
const auth = useAuthStore()

const kode = ref('')
const kirim = ref(false)
const pesanGagal = ref('')

async function selesai(kodeIsi) {
  kirim.value = true
  pesanGagal.value = ''
  try {
    const user = await auth.simpanReferal(kodeIsi)
    router.replace(dashboardFor(user.role))
  } catch (err) {
    pesanGagal.value =
      err.response?.data?.errors?.kode_referal?.[0] ??
      err.response?.data?.message ??
      'Kode referal belum bisa disimpan. Coba lagi.'
  } finally {
    kirim.value = false
  }
}

function simpan() {
  selesai(kode.value)
}

function lewati() {
  selesai('')
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-[#f0f5fb] px-[18px]">
    <section
      class="w-full max-w-[420px] rounded-[9px] border border-[#dce4ed] bg-white p-7 shadow-[0_12px_35px_rgb(21_48_80/7%)]"
    >
      <h1 class="font-['Space_Grotesk'] text-xl font-extrabold text-[#172033]">
        Punya kode referal?
      </h1>
      <p class="mt-1 text-xs leading-[1.6] text-[#68758a]">
        Masukkan kode dari orang yang mengundang Anda. Opsi ini hanya tersedia sekali; setelah
        disimpan atau dilewati, kode tidak bisa diubah.
      </p>

      <form class="mt-5 grid gap-3" @submit.prevent="simpan">
        <label class="grid gap-1 text-[12px] font-[650] text-[#172033]">
          Kode referal
          <input
            v-model="kode"
            type="text"
            maxlength="255"
            autocomplete="off"
            class="min-h-11 rounded-md border border-[#dce4ed] px-3 text-[13px] uppercase outline-none focus:border-[#0759a5]"
          />
        </label>

        <p v-if="pesanGagal" role="alert" class="text-xs text-[#c54c45]">{{ pesanGagal }}</p>

        <button
          type="submit"
          :disabled="kirim || kode.trim() === ''"
          class="flex min-h-11 w-full items-center justify-center rounded-md bg-[#0759a5] text-[13px] font-[750] text-white hover:bg-[#064d91] disabled:opacity-60"
        >
          Simpan kode
        </button>
        <button
          type="button"
          :disabled="kirim"
          class="flex min-h-11 w-full items-center justify-center rounded-md border border-[#e2e8f0] bg-[#f1f4f8] text-[13px] font-[750] text-[#182238] hover:bg-[#e8edf3] disabled:opacity-60"
          @click="lewati"
        >
          Lewati
        </button>
      </form>
    </section>
  </main>
</template>
