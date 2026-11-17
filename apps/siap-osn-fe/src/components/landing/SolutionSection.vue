<script setup>
import './shared.css'
import FeaturesSection from './FeaturesSection.vue'
</script>

<template>
  <section id="fitur" class="font-body bg-white text-[#0b1f4b]">
    <div class="mx-auto max-w-[1250px] px-6 pt-[39px] lg:px-0">
      <div class="flex flex-col items-center text-center">
        <span class="flex h-[75px] w-[75px] items-center justify-center rounded-full bg-[#f6d77a] text-[#0b1f4b]">
          <svg viewBox="0 0 24 24" class="h-[26px] w-[26px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14" /><path d="m19 12-7 7-7-7" />
          </svg>
        </span>
        <p class="mt-[28px] text-[20px] leading-[28px] text-black">Tiga masalah itu kami jawab lewat enam fitur belajar</p>
        <h2 class="font-display mt-[7px] text-[34px] font-bold leading-[1.25] lg:text-[48px] lg:leading-[60px]">
          Semua yang kamu butuhkan<br />untuk satu jalur persiapan
        </h2>
      </div>

      <FeaturesSection />
    </div>
  </section>
</template>
