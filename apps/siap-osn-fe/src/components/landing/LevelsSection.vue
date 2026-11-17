<script setup>
import './shared.css'

const check = '<path d="M20 6 9 17l-5-5"/>'
const kabupaten = ['Pre-test dasar', 'Materi fondasi', 'Simulasi seleksi']
const provinsi = ['Pemetaan kompetensi lanjutan', 'Rekomendasi personal', 'Pembahasan tiap soal']
</script>

<template>
  <section id="tingkat" class="font-body bg-[#eef2f8] px-6 pb-[66px] pt-[77px] text-[#0b1f4b]">
    <div class="mx-auto max-w-[1250px] text-center">
      <h2 class="font-display text-[30px] font-bold leading-[48px] lg:text-[40px]">Naik tingkat sesuai pencapaianmu</h2>
      <p class="mt-px text-[16px] leading-[24px] text-[#5b6785]">
        Tingkat berikutnya terbuka otomatis setelah kamu memenuhi ketentuan pada tingkat sebelumnya.
      </p>

      <div class="mt-[50px] flex flex-col items-center justify-center gap-6 lg:flex-row lg:gap-[39px]">
        <!-- 01 Kabupaten -->
        <article
          class="flex h-[406px] w-full max-w-[370px] flex-col rounded-[32px] border border-[#e6eaf3] bg-white px-[26px] pb-[26px] pt-[28px] text-left shadow-[0_6px_24px_rgba(11,31,75,0.04)]"
        >
          <span class="font-display text-[34px] font-bold leading-[40px] text-[#0b1f4b]">01</span>
          <h3 class="font-display mt-[6px] text-[24px] font-bold leading-[30px]">Kabupaten</h3>
          <p class="mt-[7px] text-[15px] leading-[23px] text-[#5b6785]">
            Titik awal persiapan, membangun fondasi kompetensi dasar Informatika.
          </p>
          <ul class="mt-[19px] flex flex-col gap-3 text-[15px] font-medium">
            <li v-for="t in kabupaten" :key="t" class="flex h-5 items-center gap-[10px]">
              <span class="flex h-[19px] w-[19px] items-center justify-center rounded-full bg-[#0b1f4b] text-white">
                <svg viewBox="0 0 24 24" class="h-[11px] w-[11px]" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" v-html="check" />
              </span>
              {{ t }}
            </li>
          </ul>
          <a href="/register" class="mt-auto flex h-[49px] items-center justify-center rounded-full border border-[#0d3a96] text-[15px] font-semibold">
            Mulai
          </a>
        </article>

        <!-- Panah -->
        <span class="flex h-[62px] w-[62px] shrink-0 rotate-90 items-center justify-center rounded-full bg-[#f6d77a] text-[#0b1f4b] lg:rotate-0">
          <svg viewBox="0 0 24 24" class="h-[24px] w-[24px]" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
          </svg>
        </span>

        <!-- 02 Provinsi -->
        <article class="flex h-[406px] w-full max-w-[370px] flex-col rounded-[32px] bg-[#0d3a96] px-[26px] pb-[26px] pt-[28px] text-left text-white">
          <span class="font-display text-[34px] font-bold leading-[40px] text-[#f6d77a]">02</span>
          <h3 class="font-display mt-[6px] text-[24px] font-bold leading-[30px]">Provinsi</h3>
          <p class="mt-[7px] text-[15px] leading-[23px] text-white/75">
            Materi dan simulasi meningkat mengikuti standar seleksi tingkat provinsi.
          </p>
          <ul class="mt-[19px] flex flex-col gap-3 text-[15px] font-medium">
            <li v-for="t in provinsi" :key="t" class="flex h-5 items-center gap-[10px]">
              <span class="flex h-[19px] w-[19px] items-center justify-center rounded-full bg-[#f6d77a] text-[#0d3a96]">
                <svg viewBox="0 0 24 24" class="h-[11px] w-[11px]" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" v-html="check" />
              </span>
              {{ t }}
            </li>
          </ul>
          <a href="/register" class="mt-auto flex h-[49px] items-center justify-center rounded-full bg-white text-[15px] font-semibold text-[#0b1f4b]">
            Mulai
          </a>
        </article>
      </div>
    </div>
  </section>
</template>
