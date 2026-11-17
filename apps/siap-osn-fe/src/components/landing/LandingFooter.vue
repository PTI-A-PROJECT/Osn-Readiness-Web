<script setup>
import './shared.css'

const cols = [
  { title: 'Produk', links: [['Fitur', '#fitur'], ['Cara Kerja', '#cara-kerja'], ['Tingkat Seleksi', '#tingkat']] },
  { title: 'Tentang', links: [['Tim Kami', '#'], ['Kenapa SIAP OSN', '#kenapa']] },
  { title: 'Mulai', links: [['Daftar Gratis', '/register'], ['Masuk', '/login']] },
]
</script>

<template>
  <footer class="font-body bg-white px-6 pb-[97px] pt-[91px] text-[#0b1f4b] lg:px-0">
    <div class="mx-auto max-w-[1245px]">
      <div class="grid gap-10 lg:grid-cols-[430px_298px_298px_1fr]">
        <div>
          <a href="#beranda" class="flex items-center gap-2">
            <img src="/logo-siap-osn.png" alt="SIAP OSN" class="h-[27px] w-auto" />
            <span class="font-display text-[17px] font-bold leading-none">SIAP OSN</span>
          </a>
          <p class="mt-[11px] max-w-[340px] text-[14px] leading-[22px] text-[#5b6785]">
            Sistem Interaktif Adaptif Persiapan OSN — membantu siswa mempersiapkan diri menghadapi seleksi OSN Bidang
            Informatika secara adaptif dan terukur.
          </p>
        </div>

        <div v-for="c in cols" :key="c.title">
          <h4 class="text-[14px] font-bold leading-[22px]">{{ c.title }}</h4>
          <ul class="mt-[12px] flex flex-col gap-[7px] text-[14px] leading-[22px] text-[#5b6785]">
            <li v-for="l in c.links" :key="l[0]"><a :href="l[1]" class="hover:text-[#0b1f4b]">{{ l[0] }}</a></li>
          </ul>
        </div>
      </div>

      <div class="mt-[40px] flex justify-between border-t border-[#eaedf5] pt-[18px] text-[13px] text-[#5b6785]">
        <span>© 2026 SIAP OSN. Seluruh hak cipta dilindungi.</span>
        <span>Malang, Indonesia</span>
      </div>
    </div>
  </footer>
</template>
