<script setup>
import './shared.css'

/* Ubah path gambar di sini jika file ilustrasimu ada di folder lain */
const steps = [
  { n: 1, title: 'Daftar akun', desc: 'Isi nama, email, dan password. Punya kode referral dari teman yang mengundangmu? Masukkan saat mendaftar (opsional).', img: '/step-1.png', h: 'h-[256px]', numW: 'w-[90px]', titleColor: 'text-[#0b1f4b]' },
  { n: 2, title: 'Kerjakan pre-test', desc: 'Pre-test adaptif mengukur kemampuan awalmu sesuai tingkat seleksi yang kamu ikuti.', img: '/step-2.png', h: 'h-[259px]', numW: 'w-[115px]', titleColor: 'text-[#1a1a1a]' },
  { n: 3, title: 'Pemetaan & belajar materi personal', desc: 'Sistem memetakan kompetensimu, lalu merekomendasikan materi yang sudah dipersonalisasi agar belajar lebih terarah.', img: '/step-3.png', h: 'h-[258px]', numW: 'w-[121px]', titleColor: 'text-[#1a1a1a]' },
  { n: 4, title: 'Simulasi bertingkat', desc: 'Uji kesiapanmu lewat simulasi seleksi, mulai dari tingkat Kabupaten lalu naik ke Provinsi.', img: '/step-4.png', h: 'h-[278px]', numW: 'w-[126px]', titleColor: 'text-[#1a1a1a]' },
]
</script>

<template>
  <section id="cara-kerja" class="font-body relative overflow-hidden bg-white pb-[106px] text-[#0b1f4b]">
    <!-- Logo latar (watermark), sama seperti di Figma -->
    <div
      class="pointer-events-none absolute left-1/2 top-0 hidden h-full w-[1440px] -translate-x-1/2 lg:block"
      aria-hidden="true"
    >
      <img
        src="/logo-bg.png"
        alt=""
        class="absolute right-0 top-[493px] w-[1150px] max-w-none select-none"
      />
    </div>

    <div class="relative mx-auto max-w-[1250px] px-6 pt-[133px] lg:px-0">
      <h2 class="font-display text-center text-[34px] font-bold leading-[1.25] lg:text-[48px] lg:leading-[60px]">
        Cara Kerja <span class="text-[#4f8ef7]">Siap OSN</span>
      </h2>

      <div class="mt-[19px] flex flex-col gap-10 lg:gap-[50px]">
        <div
          v-for="(s, i) in steps"
          :key="s.n"
          class="flex flex-col items-center gap-6 lg:items-center lg:gap-0"
          :class="i % 2 ? 'lg:flex-row-reverse' : 'lg:flex-row'"
        >
          <!-- Teks + angka -->
          <div
            class="flex items-center"
            :class="i % 2 ? 'lg:ml-[53px] lg:flex-1' : 'lg:ml-[8px] lg:w-[755px]'"
          >
            <span
              class="font-display shrink-0 select-none text-[120px] font-bold leading-none text-[#ffe3c4] lg:text-[188px]"
              :class="s.numW"
            >
              {{ s.n }}
            </span>
            <div class="max-w-[640px]">
              <h3 class="text-[24px] font-bold leading-[30px]" :class="s.titleColor">{{ s.title }}</h3>
              <p class="mt-[10px] text-[18px] leading-[30px] text-[#495057] lg:text-[20px]">{{ s.desc }}</p>
            </div>
          </div>

          <!-- Ilustrasi -->
          <div
            class="flex justify-center"
            :class="i % 2 ? 'lg:w-[432px]' : 'lg:ml-auto lg:w-[455px]'"
          >
            <img :src="s.img" :alt="s.title" class="w-auto max-w-full object-contain" :class="s.h" />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
