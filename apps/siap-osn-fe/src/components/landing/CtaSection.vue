<script setup>
import './shared.css'

/* Latar abu-biru berhenti di tengah banner (sama seperti di Figma) */
const band = { background: 'linear-gradient(to bottom, #eef2f8 0, #eef2f8 216px, #ffffff 216px)' }
const ctaBg = { background: 'linear-gradient(to top right, #5b8ff0 0%, #0d3996 50%, #2560db 100%)' }
</script>

<template>
  <section class="font-body px-3 lg:px-0" :style="band">
    <div
      class="mx-auto max-w-[1245px] rounded-[28px] px-6 py-14 text-center text-white lg:rounded-[40px] lg:pb-[73px] lg:pt-[72px]"
      :style="ctaBg"
    >
      <h2 class="font-display text-[32px] font-bold leading-[1.2] lg:text-[44px] lg:leading-[52px]">
        Siap hadapi seleksi OSN<br />Informatika?
      </h2>
      <p class="mt-[14px] text-[16px] leading-6">Mulai dengan pre-test gratis dan lihat langsung peta kompetensimu.</p>
      <div class="mt-[26px] flex flex-wrap justify-center gap-[10px]">
        <a href="/register" class="flex h-[50px] items-center rounded-full bg-[#0b1f4b] px-[25px] text-[15px] font-semibold">
          Daftar Gratis
        </a>
        <a href="/login" class="flex h-[50px] items-center rounded-full bg-white px-[25px] text-[15px] font-semibold text-[#0b1f4b]">
          Sudah punya akun? Masuk
        </a>
      </div>
    </div>
  </section>
</template>
