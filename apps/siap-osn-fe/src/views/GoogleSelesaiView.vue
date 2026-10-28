<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { dashboardFor } from '@/router/index.js'
import { useAuthStore } from '@/stores/auth.js'
import { pesanExchangeGagal, PESAN_GOOGLE_GAGAL, urlRedirectGoogle } from '@/lib/oauth.js'

// Halaman publik tujuan callback backend: keadaan memproses, berhasil,
// gagal. Kode atau kode kegagalan dibaca dari fragmen URL; keduanya
// langsung dihapus dari address bar.
const router = useRouter()
const auth = useAuthStore()

const keadaan = ref('memproses') // memproses | berhasil | gagal
const pesanGagal = ref(PESAN_GOOGLE_GAGAL.lain)

function bacaFragmen() {
  const fragmen = new URLSearchParams(window.location.hash.slice(1))
  return {
    kode: fragmen.get('code') ?? '',
    kodeGagal: fragmen.get('error') ?? '',
  }
}

async function tukarkan(kode) {
  try {
    const user = await auth.masukDenganGoogle(kode)
    keadaan.value = 'berhasil'
    // Beri waktu keadaan terbaca sebelum pindah halaman dashboard.
    setTimeout(() => router.replace(dashboardFor(user.role)), 800)
  } catch (err) {
    keadaan.value = 'gagal'
    pesanGagal.value = pesanExchangeGagal(err.response?.data?.kode)
  }
}

onMounted(() => {
  const { kode, kodeGagal } = bacaFragmen()
  // Fragmen tidak pernah dikirim browser ke server, jadi pastinya dihapus
  // di browser: replaceState menyelimuti entry riwayat yang sama agar
  // tombol kembali tidak mengulang alur dengan kode yang sudah dipakai.
  history.replaceState(null, '', window.location.pathname)

  if (kodeGagal) {
    keadaan.value = 'gagal'
    pesanGagal.value = PESAN_GOOGLE_GAGAL[kodeGagal] ?? PESAN_GOOGLE_GAGAL.lain
    return
  }

  tukarkan(kode)
})

function cobaGoogleLagi() {
  window.location.assign(urlRedirectGoogle())
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-[#f0f5fb] px-[18px]">
    <section
      class="w-full max-w-[420px] rounded-[9px] border border-[#dce4ed] bg-white p-7 text-center shadow-[0_12px_35px_rgb(21_48_80/7%)]"
      aria-live="polite"
    >
      <!-- Memproses -->
      <template v-if="keadaan === 'memproses'">
        <span
          class="mx-auto block h-7 w-7 animate-spin rounded-full border-[3px] border-[#d6e0eb] border-t-[#0759a5]"
          aria-hidden="true"
        ></span>
        <h1 class="mt-4 font-['Space_Grotesk'] text-xl font-extrabold text-[#172033]">
          Menyelesaikan login Google
        </h1>
        <p class="mt-1 text-xs leading-[1.5] text-[#68758a]">
          Halaman ini menukar kode sementara dengan data akun, lalu langsung
          memindahkan Anda.
        </p>
      </template>

      <!-- Berhasil -->
      <template v-else-if="keadaan === 'berhasil'">
        <span
          class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-[#16a34a]/10 text-[16px] text-[#16a34a]"
          aria-hidden="true"
          >✓</span
        >
        <h1 class="mt-4 font-['Space_Grotesk'] text-xl font-extrabold text-[#172033]">
          Login berhasil
        </h1>
        <p class="mt-1 text-xs leading-[1.5] text-[#68758a]">Mengalihkan ke dashboard...</p>
      </template>

      <!-- Gagal -->
      <template v-else>
        <span
          class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-[#c54c45]/10 text-[16px] text-[#c54c45]"
          aria-hidden="true"
          >!</span
        >
        <h1 class="mt-4 font-['Space_Grotesk'] text-xl font-extrabold text-[#172033]">
          Login Google tidak selesai
        </h1>
        <p class="mt-1 text-xs leading-[1.6] text-[#68758a]">{{ pesanGagal }}</p>
        <div class="mt-5 grid gap-2">
          <button
            type="button"
            class="flex min-h-11 w-full items-center justify-center rounded-md bg-[#0759a5] text-[13px] font-[750] text-white hover:bg-[#064d91]"
            @click="cobaGoogleLagi"
          >
            Coba Google lagi
          </button>
          <RouterLink
            to="/login"
            class="flex min-h-11 w-full items-center justify-center rounded-md border border-[#e2e8f0] bg-[#f1f4f8] text-[13px] font-[750] text-[#182238] hover:bg-[#e8edf3]"
          >
            Masuk dengan email
          </RouterLink>
        </div>
      </template>
    </section>
  </main>
</template>
