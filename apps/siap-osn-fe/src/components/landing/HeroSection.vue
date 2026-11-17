<script setup>
import './shared.css'
import { ref, onMounted, onBeforeUnmount } from 'vue'

const navItems = [
  { id: 'beranda', label: 'Beranda' },
  { id: 'kenapa', label: 'Kenapa SIAP OSN' },
  { id: 'fitur', label: 'Fitur' },
  { id: 'cara-kerja', label: 'Cara Kerja' },
  { id: 'tingkat', label: 'Tingkat Seleksi' },
]

/* Scroll spy: menu aktif mengikuti section yang sedang terlihat */
const active = ref('beranda')
let observer
onMounted(() => {
  observer = new IntersectionObserver(
    (entries) => entries.forEach((e) => e.isIntersecting && (active.value = e.target.id)),
    { rootMargin: '-45% 0px -50% 0px' },
  )
  navItems.forEach((n) => {
    const el = document.getElementById(n.id)
    if (el) observer.observe(el)
  })
})
onBeforeUnmount(() => observer?.disconnect())

const heroBg = {
  backgroundColor: '#eef1f8',
  backgroundImage:
    'radial-gradient(ellipse 55% 50% at 17% 95%, #cfe0ff 0%, rgba(207,224,255,0) 100%),' +
    'radial-gradient(ellipse 50% 55% at 95% 88%, #fbf0cf 0%, rgba(251,240,207,0) 100%)',
}
</script>

<template>
  <section id="beranda" class="font-body bg-white text-[#0b1f4b]">
    <div
      class="mx-3 lg:mx-[46px] rounded-b-[48px] lg:rounded-b-[90px] overflow-hidden"
      :style="heroBg"
    >
      <!-- Navbar -->
      <header class="relative flex h-[48px] items-center justify-between px-6 lg:px-[50px] mt-[30px]">
        <a href="#beranda" class="flex items-center gap-[9px]">
          <img src="/logo-siap-osn.png" alt="SIAP OSN" class="h-[34px] w-auto" />
          <span class="font-display text-[18px] font-bold leading-none">SIAP OSN</span>
        </a>

        <nav class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-1 text-[14px] text-[#5b6785] lg:flex">
          <a
            v-for="n in navItems"
            :key="n.id"
            :href="`#${n.id}`"
            class="rounded-full px-[14px] py-[7px] leading-[21px] transition-colors"
            :class="active === n.id ? 'bg-white font-bold text-[#0b1f4b] shadow-[0_2px_8px_rgba(11,31,75,0.08)]' : 'hover:text-[#0b1f4b]'"
          >
            {{ n.label }}
          </a>
        </nav>

        <a
          href="/register"
          class="flex h-[48px] items-center rounded-full bg-[#0b1f4b] px-[23px] text-[15px] font-semibold text-white"
        >
          Daftar Gratis
        </a>
      </header>

      <!-- Konten hero -->
      <div class="flex flex-col items-start justify-between px-6 pb-10 pt-12 lg:flex-row lg:px-[50px] lg:pb-[17px] lg:pt-[79px]">
        <div class="lg:flex-1">
          <span
            class="inline-flex h-[40px] items-center rounded-full border border-[#879fdc] bg-white/30 px-[18px] text-[16px] font-medium"
          >
            Persiapan OSN Bidang Informatika
          </span>

          <h1 class="font-display mt-[22px] max-w-[680px] text-[40px] font-bold leading-[1.2] lg:text-[54px] lg:leading-[73px]">
            Sistem Interaktif Adaptif Persiapan OSN
          </h1>

          <p class="mt-[16px] max-w-[580px] text-[17px] leading-[27.5px] text-[#5b6785]">
            Persiapkan dirimu menghadapi seleksi OSN Bidang Informatika secara adaptif dari tingkat Kabupaten hingga
            Nasional dengan pemetaan kompetensi otomatis dan materi yang dipersonalisasi.
          </p>

          <div class="mt-[38px] flex flex-wrap gap-3">
            <a href="/register" class="flex h-[54px] items-center rounded-full bg-[#0b1f4b] px-[26px] text-[16px] font-semibold text-white">
              Mulai Pre-Test Gratis
            </a>
            <a href="#cara-kerja" class="flex h-[54px] items-center rounded-full bg-white px-[26px] text-[16px] font-semibold shadow-[0_2px_10px_rgba(11,31,75,0.06)]">
              Lihat Cara Kerja
            </a>
          </div>
        </div>

        <!-- Maskot + kartu statistik -->
        <div class="relative mx-auto mt-10 h-[320px] w-full max-w-[570px] lg:mt-[58px] lg:h-[465px] lg:w-[570px] lg:shrink-0">
          <img
            src="/mascot-hero.png"
            alt="Maskot SIAP OSN"
            class="absolute -right-[10px] top-0 h-full w-auto object-contain"
          />

          <div class="stat-card left-[139px] top-[36px] h-[82px] w-[141px]">
            <b class="font-display text-[44px] leading-none">2</b>
            <span class="text-[16px] font-medium leading-[22px]">Tingkat<br />Seleksi</span>
          </div>
          <div class="stat-card left-[72px] top-[163px] h-[89px] w-[162px]">
            <b class="font-display text-[44px] leading-none">6</b>
            <span class="text-[16px] font-medium leading-[22px]">Kelompok<br />Fitur<br />Belajar</span>
          </div>
          <div class="stat-card left-[7px] top-[298px] h-[116px] w-[265px]">
            <b class="font-display text-[40px] leading-none">100%</b>
            <span class="text-[16px] font-medium leading-[22px]">Materi<br />Dipersonalisasi</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.stat-card {
  position: absolute;
  display: none;
  align-items: center;
  gap: 12px;
  padding: 0 26px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.55);
  border: 1px solid #f1d9bf;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 24px rgba(11, 31, 75, 0.06);
  color: #0b1f4b;
}
@media (min-width: 1024px) {
  .stat-card {
    display: flex;
  }
}
</style>
