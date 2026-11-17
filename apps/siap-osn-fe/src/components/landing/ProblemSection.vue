<script setup>
import './shared.css'

const icons = {
  layers:
    '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
  book: '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
  laptop:
    '<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/>',
}

const problems = [
  { icon: 'layers', title: 'Seleksi Bertahap', desc: 'Tiga jenjang dengan standar berbeda di tiap tingkat.' },
  { icon: 'book', title: 'Materi Sangat Luas', desc: 'Terlalu banyak topik informatika, sulit menentukan prioritas.' },
  { icon: 'laptop', title: 'Minim Simulasi Realistis', desc: 'Sulit berlatih dalam kondisi yang menyerupai seleksi sesungguhnya.' },
]
</script>

<template>
  <section id="kenapa" class="font-body bg-white text-[#0b1f4b]">
    <div class="mx-auto max-w-[1250px] px-6 pt-[63px] lg:px-0">
      <div class="grid gap-4 lg:grid-cols-[660px_1fr]">
        <h2 class="font-display text-[34px] font-bold leading-[1.25] lg:text-[48px] lg:leading-[60px]">
          Kenapa Persiapan OSN<br />itu Sulit?
        </h2>
        <p class="max-w-[590px] text-[18px] leading-[27px] text-black lg:mt-[17px] lg:text-[21px]">
          Jenjang seleksi bertahap, materi yang luas, dan minimnya simulasi realistis membuat siswa sulit tahu harus
          fokus belajar dari mana.
        </p>
      </div>

      <div class="mt-[62px] grid gap-[38px] md:grid-cols-3 lg:mx-[5px]">
        <article
          v-for="p in problems"
          :key="p.title"
          class="h-[302px] rounded-[28px] border border-[#2462ea] bg-white p-[26px]"
        >
          <span class="flex h-[84px] w-[84px] items-center justify-center rounded-[20px] bg-[#0d3a96] text-white">
            <svg viewBox="0 0 24 24" class="h-[38px] w-[38px]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" v-html="icons[p.icon]" />
          </span>
          <h3 class="mt-[38px] text-[26px] font-bold leading-[32px] text-black">{{ p.title }}</h3>
          <p class="mt-[10px] text-[16px] leading-[21px] text-[#5b6785]">{{ p.desc }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
