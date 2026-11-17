<script setup>
import './shared.css'

/* grad: kartu bergradasi biru + bayangan | flat: kartu abu muda polos */
const columns = [
  [
    { grad: true, h: 'lg:h-[296px]', title: 'Pre-test Adaptif', desc: 'Mengukur kemampuan awal sesuai tingkat seleksi yang kamu pilih, sebelum masuk ke materi.', tag: 'Menjawab: materi luas' },
    { grad: false, h: 'lg:h-[244px]', title: 'Simulasi Seleksi Bertingkat', desc: 'Rasakan simulasi ujian yang merepresentasikan kondisi seleksi sesungguhnya di tiap tingkat.', tag: 'Menjawab: seleksi bertahap & simulasi' },
  ],
  [
    { grad: false, h: 'lg:h-[245px]', title: 'Pemetaan Kompetensi Otomatis', desc: 'Menunjukkan mana yang sudah kuat dan mana yang masih perlu ditingkatkan.', tag: 'Menjawab: materi luas' },
    { grad: true, h: 'lg:h-[296px]', title: 'Pembahasan & Evaluasi Tiap Soal', desc: 'Setiap soal dilengkapi pembahasan agar kamu tahu letak kesalahan dan cara memperbaikinya.', tag: 'Menjawab: minim simulasi' },
  ],
  [
    { grad: true, h: 'lg:h-[296px]', title: 'Rekomendasi Materi Personal', desc: 'Belajar difokuskan pada materi yang paling kamu butuhkan, bukan topik yang sudah dikuasai.', tag: 'Menjawab: materi luas' },
    { grad: false, h: 'lg:h-[244px]', title: 'Dashboard Progress Belajar', desc: 'Pantau riwayat pre-test, latihan, dan simulasi dalam satu tempat.', tag: 'Menjawab: seleksi bertahap' },
  ],
]

const gradStyle = { background: 'linear-gradient(to top right, #ffffff 42%, #cfdcfd 100%)' }
</script>

<template>
  <div class="mt-[52px] grid gap-6 lg:mx-[7px] lg:grid-cols-3">
    <div
      v-for="(col, ci) in columns"
      :key="ci"
      class="flex flex-col gap-6"
      :class="ci === 1 ? 'lg:gap-[21px] lg:pt-[51px]' : 'lg:h-[613px] lg:justify-between lg:gap-0'"
    >
      <article
        v-for="c in col"
        :key="c.title"
        class="flex flex-col justify-between gap-6 rounded-[26px] border border-[#e7eaf3] p-7"
        :class="[c.h, c.grad ? 'shadow-[0_14px_34px_rgba(11,31,75,0.07)]' : 'bg-[#f6f7fb]']"
        :style="c.grad ? gradStyle : null"
      >
        <h3 class="font-display text-[24px] font-bold leading-[27px] text-[#0b1f4b]">{{ c.title }}</h3>
        <div>
          <p class="text-[15px] leading-[24px] text-[#5b6785]">{{ c.desc }}</p>
          <p class="mt-[10px] text-[13px] font-semibold leading-4 text-[#2563eb]">{{ c.tag }}</p>
        </div>
      </article>
    </div>
  </div>
</template>
