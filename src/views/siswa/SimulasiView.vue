<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserMenu from '@/components/UserMenu.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const levels = [
  { key: 'kabupaten', nama: 'Kabupaten', status: 'selesai', label: 'Sudah Dilalui',
    desc: 'Fondasi dasar Informatika. Kamu sudah menuntaskan tingkat ini.', soal: 25, menit: 60 },
  { key: 'provinsi', nama: 'Provinsi', status: 'berjalan', label: 'Sedang Berjalan',
    desc: 'Standar soal meningkat mengikuti seleksi tingkat provinsi.', soal: 30, menit: 90 },
  { key: 'nasional', nama: 'Nasional', status: 'terkunci', label: 'Terkunci',
    desc: 'Selesaikan 3 materi prioritas & raih nilai ≥ 80 di Provinsi untuk membuka.', soal: 35, menit: 120 },
]

const hasil = {
  skor: 74,
  judul: 'Simulasi Seleksi — Tingkat Provinsi',
  meta: '22 September 2026 · 25 dari 30 benar · Durasi 78 menit',
  stats: [
    { nilai: '25', label: 'Benar' },
    { nilai: '5', label: 'Salah' },
    { nilai: '78m', label: 'Durasi' },
    { nilai: '+5', label: 'Dari sebelumnya' },
  ],
}

const pembahasan = [
  { no: 1, judul: 'Kompleksitas Algoritma Sorting', benar: true, jawaban: 'O(n log n)',
    penjelasan: 'Merge Sort dan Quick Sort (rata-rata kasus) memiliki kompleksitas O(n log n), lebih efisien dibanding Bubble Sort yang O(n²).' },
  { no: 7, judul: 'Dynamic Programming: Knapsack', benar: false, jawaban: 'Greedy Algorithm', kunci: 'Dynamic Programming',
    penjelasan: 'Masalah 0/1 Knapsack tidak bisa diselesaikan optimal dengan greedy karena keputusan satu item memengaruhi item lain. Dynamic Programming menjamin solusi optimal dengan menyimpan sub-solusi.' },
  { no: 15, judul: 'Traversal Graf: BFS vs DFS', benar: false, jawaban: 'DFS', kunci: 'BFS',
    penjelasan: 'Untuk lintasan terpendek pada graf tak berbobot, BFS tepat karena menjelajah berdasarkan level kedalaman sehingga node pertama yang ditemukan adalah yang terdekat.' },
  { no: 22, judul: 'Struktur Data: Stack & Queue', benar: true, jawaban: 'Queue (FIFO)',
    penjelasan: 'Queue mengikuti prinsip First In First Out, cocok untuk kasus antrian seperti pada soal ini.' },
]

// Lingkaran skor (SVG)
const R = 36
const keliling = 2 * Math.PI * R
const offset = computed(() => keliling * (1 - hasil.skor / 100))

function mulai(level) {
  // Halaman ujian belum dibuat. Kalau nanti ada route 'siswa.ujian', otomatis dipakai.
  if (router.hasRoute('siswa.ujian')) {
    router.push({ name: 'siswa.ujian', params: { level: level.key } })
    return
  }
  toast.add({
    severity: 'info',
    summary: 'Segera hadir',
    detail: `Halaman ujian tingkat ${level.nama} belum tersedia.`,
    life: 3000,
  })
}

function lihatRiwayat() {
  router.push({ name: 'siswa.riwayat' })
}
</script>

<template>
  <div class="halaman">
    <header class="topbar">
      <div>
        <h1>{{ route.meta.title || 'Simulasi Seleksi' }}</h1>
        <p>Rasakan kondisi ujian sesungguhnya, sesuai standar TOKI</p>
      </div>
      <UserMenu />
    </header>

    <div class="konten">
    <!-- 01 Pilih tingkat -->
    <section>
      <span class="eyebrow">01 · Pilih Tingkat</span>
      <h2>Pilih Tingkat Simulasi</h2>
      <p class="sub">Tekan Mulai Simulasi untuk membuka halaman ujian.</p>

      <div class="grid-level">
        <article v-for="l in levels" :key="l.key" class="kartu level" :class="'is-' + l.status">
          <span class="badge" :class="'badge-' + l.status">
            <template v-if="l.status === 'terkunci'">🔒 </template>{{ l.label }}
          </span>
          <h3>{{ l.nama }}</h3>
          <p class="desc">{{ l.desc }}</p>
          <div class="info">
            <span>📝 {{ l.soal }} soal</span>
            <span>⏱ {{ l.menit }} menit</span>
          </div>
          <button v-if="l.status === 'selesai'" type="button" class="btn btn-hijau" @click="lihatRiwayat">Lihat Riwayat</button>
          <button v-else-if="l.status === 'berjalan'" type="button" class="btn btn-emas" @click="mulai(l)">Mulai Simulasi →</button>
          <button v-else type="button" class="btn btn-mati" disabled>Terkunci</button>
        </article>
      </div>
    </section>

    <hr />

    <!-- 02 Hasil terakhir -->
    <section>
      <span class="eyebrow">02 · Hasil Terakhir</span>
      <h2>Hasil Simulasi Terakhir</h2>
      <p class="sub">Ringkasan skor dari simulasi yang baru kamu selesaikan.</p>

      <div class="kartu hasil">
        <div class="hasil-atas">
          <div class="ring">
            <svg viewBox="0 0 88 88" width="80" height="80">
              <circle cx="44" cy="44" :r="R" fill="none" stroke="#f1f3f7" stroke-width="8" />
              <circle cx="44" cy="44" :r="R" fill="none" stroke="#f0b429" stroke-width="8" stroke-linecap="round"
                :stroke-dasharray="keliling" :stroke-dashoffset="offset" transform="rotate(-90 44 44)" />
            </svg>
            <div class="ring-teks"><strong>{{ hasil.skor }}%</strong><small>Skor</small></div>
          </div>
          <div>
            <h3>{{ hasil.judul }}</h3>
            <p class="meta">{{ hasil.meta }}</p>
          </div>
        </div>
        <div class="stats">
          <div v-for="s in hasil.stats" :key="s.label" class="stat">
            <strong>{{ s.nilai }}</strong><span>{{ s.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <hr />

    <!-- 03 Pembahasan -->
    <section>
      <span class="eyebrow">03 · Pembahasan</span>
      <h2>Pembahasan Soal</h2>
      <p class="sub">Jawabanmu dibandingkan dengan jawaban benar, lengkap dengan penjelasannya.</p>

      <div class="daftar">
        <article v-for="p in pembahasan" :key="p.no" class="kartu soal">
          <span class="ikon-hasil" :class="p.benar ? 'ok' : 'salah'">{{ p.benar ? '✓' : '✕' }}</span>
          <div>
            <h3>Soal {{ p.no }} — {{ p.judul }}</h3>
            <p class="jawab">
              Jawabanmu: <b>{{ p.jawaban }}</b>
              <template v-if="p.benar"> — <span class="hijau">Benar</span></template>
              <template v-else> · Jawaban benar: <b>{{ p.kunci }}</b></template>
            </p>
            <p class="penjelasan">{{ p.penjelasan }}</p>
          </div>
        </article>
      </div>
    </section>
    </div>
  </div>
</template>

<style scoped>
.halaman { min-height: 100%; background: #f5f8fc; }
.topbar {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  background: #fff; border-bottom: 1px solid #e6ebf2; padding: 16px 28px;
}
.topbar h1 { font-size: 17px; font-weight: 700; line-height: 1.2; margin: 0; color: #0f1b33; }
.topbar p { font-size: 13px; color: #6b778c; margin: 2px 0 0; }

.konten {
  --emas: #f0b429;
  --emas-tua: #d9971a;
  --hijau: #1f9d6b;
  --merah: #e5484d;
  --ink: #101828;
  --abu: #667085;
  --garis: #e6e9f0;
  color: var(--ink);
  padding: 24px 28px 48px;
  max-width: 1100px;
}
.konten, .konten *, .konten *::before, .konten *::after { box-sizing: border-box; }

.eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .04em; color: var(--emas-tua); text-transform: uppercase; }
h2 { font-size: 16.5px; font-weight: 700; line-height: 1.3; margin: 6px 0 2px; }
h3 { font-size: 13px; font-weight: 700; margin: 0; }
.sub { font-size: 11.5px; color: var(--abu); margin: 0 0 16px; }
hr { border: 0; border-top: 1px solid var(--garis); margin: 30px 0; }

.kartu { background: #fff; border: 1px solid var(--garis); border-radius: 12px; padding: 18px; }

/* Level */
.grid-level { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; align-items: stretch; }
.level { display: flex; flex-direction: column; gap: 8px; }
.level.is-berjalan { border: 2px solid var(--emas); padding: 17px; box-shadow: 0 6px 20px rgba(240, 180, 41, .18); }
.level h3 { font-size: 15.5px; margin-top: 4px; }
.badge { align-self: flex-start; font-size: 9.5px; font-weight: 600; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
.badge-selesai { background: #e7f6ef; color: var(--hijau); }
.badge-berjalan { background: #fdf1d3; color: var(--emas-tua); }
.badge-terkunci { background: #eef1f6; color: var(--abu); }
.desc { font-size: 11px; color: var(--abu); line-height: 1.5; margin: 0; flex: 1; }
.info { display: flex; flex-wrap: nowrap; gap: 14px; font-size: 10px; color: var(--abu); margin: 4px 0 6px; white-space: nowrap; }
.btn {
  width: 100%; border: 0; border-radius: 7px; padding: 9px 10px;
  font: inherit; font-size: 11.5px; font-weight: 700; line-height: 1.2; white-space: nowrap; cursor: pointer;
}
.btn-hijau { background: #dff3e9; color: var(--hijau); }
.btn-emas { background: var(--emas); color: #1a1a1a; }
.btn-emas:hover { background: #f5c04a; }
.btn-mati { background: #eef1f6; color: var(--abu); cursor: not-allowed; }
.btn:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

/* Hasil */
.hasil { padding: 18px 18px 20px; }
.hasil-atas { display: flex; align-items: center; gap: 16px; margin-bottom: 14px; }
.ring { position: relative; width: 80px; height: 80px; flex: none; }
.ring-teks { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-teks strong { font-size: 15px; line-height: 1.1; }
.ring-teks small { font-size: 9px; color: var(--abu); }
.hasil-atas h3 { font-size: 12.5px; }
.meta { font-size: 10.5px; color: var(--abu); margin: 5px 0 0; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.stat { background: #f5f7fb; border-radius: 8px; padding: 12px 8px; text-align: center; display: flex; flex-direction: column; gap: 3px; }
.stat strong { font-size: 14px; line-height: 1.2; }
.stat span { font-size: 9.5px; color: var(--abu); }

/* Pembahasan */
.daftar { display: flex; flex-direction: column; gap: 4px; }
.soal { display: flex; gap: 14px; padding: 16px 20px; border-radius: 10px; }
.ikon-hasil { flex: none; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; font-weight: 700; }
.ikon-hasil.ok { background: #e7f6ef; color: var(--hijau); }
.ikon-hasil.salah { background: #fde8e8; color: var(--merah); }
.soal h3 { font-size: 12px; margin-top: 4px; }
.jawab { font-size: 10.5px; color: var(--abu); margin: 8px 0 10px; }
.jawab b { color: var(--ink); }
.hijau { color: var(--hijau); }
.penjelasan { font-size: 11px; line-height: 1.6; margin: 0; }

@media (max-width: 768px) {
  .topbar { padding: 14px 16px; }
  .konten { padding: 18px 16px 36px; }
  .grid-level { grid-template-columns: 1fr; }
  .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>