<script setup>
import { useRoute } from 'vue-router'
import UserMenu from '@/components/UserMenu.vue'

const route = useRoute()

const skor = 61
const R = 44
const keliling = 2 * Math.PI * R
const garis = (keliling * skor) / 100

const ringkasanTopik = [
  { nama: 'Materi 1', benar: '7/8', status: 'ok' },
  { nama: 'Materi 2', benar: '4/7', status: 'mid' },
  { nama: 'Materi 3', benar: '2/6', status: 'low' },
  { nama: 'Materi 4', benar: '1/5', status: 'low' },
  { nama: 'Materi 5', benar: '4/4', status: 'ok' },
]

const topik = [
  { nama: 'Materi 1', nilai: 82 },
  { nama: 'Materi 2', nilai: 54 },
  { nama: 'Materi 3', nilai: 38 },
  { nama: 'Materi 4', nilai: 29 },
  { nama: 'Materi 5', nilai: 96 },
  { nama: 'Materi 6', nilai: 47 },
]
const statusNilai = (n) => (n >= 70 ? 'ok' : n >= 40 ? 'mid' : 'low')
const labelStatus = { ok: 'Dikuasai', mid: 'Perlu latihan', low: 'Belum dikuasai' }

const riwayat = [
  { tgl: '02 Sep 2026', tingkat: 'Provinsi', skor: '61%', lemah: 'Materi X', tren: 'naik' },
  { tgl: '10 Agu 2026', tingkat: 'Provinsi', skor: '52%', lemah: 'Materi X', tren: 'naik' },
  { tgl: '15 Jul 2026', tingkat: 'Provinsi', skor: '47%', lemah: 'Materi X', tren: 'stabil' },
  { tgl: '28 Jun 2026', tingkat: 'Kabupaten', skor: '88%', lemah: 'Materi X', tren: 'naik' },
  { tgl: '02 Jun 2026', tingkat: 'Kabupaten', skor: '73%', lemah: 'Materi X', tren: 'turun' },
]
const teksTren = { naik: '↑ Naik', turun: '↓ Turun', stabil: '→ Stabil' }
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] text-[#0f1b33]">
    <header class="flex items-center justify-between border-b border-[#e6ebf2] bg-white px-7 py-4">
      <h1 class="text-[17px] font-bold leading-tight">{{ route.meta.title }}</h1>
      <UserMenu />
    </header>

    <main class="px-6 py-6">
      <div class="pk">

        <!-- 01 -->
        <section class="section">
          <p class="eyebrow">01 · HASIL PRE-TEST</p>
          <h2>Hasil Pre-Test Terakhir Kabupaten</h2>
          <p class="desc">Ringkasan skor dan ketepatan jawabanmu pada pre-test paling baru.</p>

          <div class="grid-2">
            <div class="card pad">
              <h3>Skor Keseluruhan</h3>
              <div class="skor-wrap">
                <div class="donut">
                  <svg viewBox="0 0 100 100" aria-hidden="true">
                    <circle cx="50" cy="50" :r="R" class="d-track" />
                    <circle
                      cx="50" cy="50" :r="R" class="d-fill"
                      :stroke-dasharray="`${garis} ${keliling}`"
                      transform="rotate(-90 50 50)"
                    />
                  </svg>
                  <div class="donut-text"><strong>{{ skor }}%</strong><span>Skor</span></div>
                </div>
                <div>
                  <p class="pt-title">Pre-Test Tingkat Kabupaten</p>
                  <p class="pt-meta">Dikerjakan 02 September 2026 · 30 soal · 55 menit</p>
                  <div class="chips">
                    <span class="chip blue">Tingkat Provinsi</span>
                    <span class="chip amber">18 dari 30 benar</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="card pad">
              <h3>Ringkasan Per Topik</h3>
              <ul class="ringkas">
                <li v-for="t in ringkasanTopik" :key="t.nama">
                  <span>{{ t.nama }}</span>
                  <span><b :class="t.status">{{ t.benar }}</b> benar</span>
                </li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 02 -->
        <section class="section">
          <p class="eyebrow">02 · GRAFIK PER TOPIK</p>
          <h2>Peta Kompetensi Saat Ini</h2>
          <p class="desc">Persentase penguasaan tiap topik, dihitung dari gabungan pre-test, latihan, dan simulasi.</p>

          <div class="card pad">
            <div class="bar-head">
              <h3>Semua Topik</h3>
              <div class="legend">
                <span><i class="dot ok"></i>Dikuasai (≥70%)</span>
                <span><i class="dot mid"></i>Perlu latihan (40–69%)</span>
                <span><i class="dot low"></i>Belum dikuasai (&lt;40%)</span>
              </div>
              <button type="button" class="link-btn">Unduh laporan</button>
            </div>

            <div v-for="t in topik" :key="t.nama" class="bar-row">
              <span class="bar-name">{{ t.nama }}</span>
              <div class="track"><div class="fill" :class="statusNilai(t.nilai)" :style="{ width: t.nilai + '%' }"></div></div>
              <span class="bar-val">{{ t.nilai }}%</span>
              <span class="bar-label" :class="statusNilai(t.nilai)">{{ labelStatus[statusNilai(t.nilai)] }}</span>
            </div>
          </div>

          <div class="card pad analisis">
            <h3 class="big">Analisis Hasil Kompetensi</h3>
            <p class="desc tight">Ringkasan berdasarkan hasil pemetaan kompetensimu saat ini.</p>

            <div class="point">
              <i class="dot ok"></i>
              <div>
                <p class="point-tag ok">KEKUATAN UTAMA</p>
                <p>Matematika Diskrit menjadi topik terkuatmu dengan penguasaan 96%. Struktur Data juga sudah dikuasai dengan 82%.</p>
              </div>
            </div>
            <div class="point">
              <i class="dot low"></i>
              <div>
                <p class="point-tag low">PERLU DIPRIORITASKAN</p>
                <p>Dynamic Programming memiliki penguasaan terendah sebesar 29%, diikuti Graf &amp; Pohon sebesar 38%.</p>
              </div>
            </div>
            <div class="point">
              <i class="dot info"></i>
              <div>
                <p class="point-tag info">SARAN BELAJAR</p>
                <p>Fokuskan latihan berikutnya pada Dynamic Programming dan Graf &amp; Pohon sebelum melanjutkan ke topik lain.</p>
              </div>
            </div>

            <div class="cta">
              <div>
                <p class="cta-title">Lanjutkan belajarmu</p>
                <p class="cta-desc">Lihat materi yang direkomendasikan berdasarkan topik yang perlu kamu tingkatkan.</p>
              </div>
              <button type="button" class="btn-amber">Lihat Rekomendasi Materi →</button>
            </div>
          </div>
        </section>

        <!-- 03 -->
        <section class="section">
          <p class="eyebrow">03 · RIWAYAT PEMETAAN</p>
          <h2>Perkembangan Pemetaan dari Waktu ke Waktu</h2>
          <p class="desc">Bandingkan skor pemetaan kompetensimu di setiap percobaan sebelumnya.</p>

          <div class="card pad">
            <h3>Riwayat Pemetaan Kompetensi</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Tanggal</th><th>Tingkat</th><th>Skor Keseluruhan</th>
                    <th>Topik Terlemah</th><th>Tren</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="r in riwayat" :key="r.tgl">
                    <td>{{ r.tgl }}</td>
                    <td>{{ r.tingkat }}</td>
                    <td><b>{{ r.skor }}</b></td>
                    <td>{{ r.lemah }}</td>
                    <td><span class="badge" :class="r.tren">{{ teksTren[r.tren] }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>
</template>

<style scoped>
.pk {
  --ok: #12b27a;
  --mid: #3b82f6;
  --low: #ef4444;
  --amber: #f59e0b;
  --gold-dark: #c8921a;
  --muted: #6b7686;
  --line: #e6ebf2;

  font-size: 13px;
}

.section { padding: 22px 0 18px; }
.section:first-child { padding-top: 0; }
.eyebrow { margin: 0 0 4px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.04em; color: var(--gold-dark); }
.section h2 { margin: 0; font-size: 17px; font-weight: 600; }
.desc { margin: 3px 0 14px; color: var(--muted); font-size: 12px; }
.desc.tight { margin-bottom: 8px; }

.card { background: #fff; border: 1px solid var(--line); border-radius: 24px; box-shadow: 0 2px 12px rgba(15, 30, 60, 0.07); }
.pad { padding: 20px 24px; }
.card h3 { margin: 0 0 14px; font-size: 13px; font-weight: 600; }
.card h3.big { font-size: 15px; margin-bottom: 4px; }
.analisis { margin-top: 16px; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Donut */
.skor-wrap { display: flex; align-items: center; gap: 18px; margin-top: 18px; }
.donut { position: relative; width: 94px; height: 94px; flex: none; }
.donut svg { width: 100%; height: 100%; }
.d-track { fill: none; stroke: #eef0f4; stroke-width: 10; }
.d-fill { fill: none; stroke: var(--amber); stroke-width: 10; stroke-linecap: round; }
.donut-text { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.donut-text strong { font-size: 20px; line-height: 1; }
.donut-text span { font-size: 10px; color: var(--muted); }
.pt-title { margin: 0; font-size: 14px; font-weight: 600; }
.pt-meta { margin: 4px 0 10px; font-size: 11px; color: var(--muted); }
.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip { padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 600; border: 1px solid transparent; }
.chip.blue { background: #e3edff; color: #1d3f8f; }
.chip.amber { background: #fff4d6; color: #9a6b00; border-color: #f7dc8e; }

/* Ringkasan topik */
.ringkas { list-style: none; margin: 0; padding: 0; }
.ringkas li { display: flex; justify-content: space-between; padding: 6px 0; font-size: 11.5px; color: #3a4558; }
.ringkas b { font-weight: 600; }
.ringkas b.ok, .bar-label.ok { color: var(--ok); }
.ringkas b.mid, .bar-label.mid { color: var(--mid); }
.ringkas b.low, .bar-label.low { color: var(--low); }

/* Grafik */
.bar-head { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; }
.bar-head h3 { margin: 0; }
.legend { display: flex; gap: 12px; flex: 1; font-size: 10px; color: var(--muted); flex-wrap: wrap; }
.legend span { display: inline-flex; align-items: center; gap: 5px; }
.dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex: none; }
.dot.ok { background: var(--ok); }
.dot.mid { background: var(--mid); }
.dot.low { background: var(--amber); }
.dot.info { background: var(--mid); }
.link-btn { border: 0; background: none; font-size: 11px; font-weight: 600; cursor: pointer; color: inherit; }

.bar-row { display: grid; grid-template-columns: 90px 1fr 40px 100px; align-items: center; gap: 14px; padding: 6px 0; font-size: 11.5px; }
.track { height: 8px; border-radius: 999px; background: #eef0f4; overflow: hidden; }
.fill { height: 100%; border-radius: 999px; }
.fill.ok { background: var(--ok); }
.fill.mid { background: var(--mid); }
.fill.low { background: linear-gradient(90deg, var(--low), var(--amber)); }
.bar-val { text-align: right; color: #3a4558; }
.bar-label { text-align: right; font-weight: 500; }

/* Analisis */
.point { display: flex; gap: 12px; padding: 12px 0 2px; }
.point .dot { margin-top: 5px; }
.point p { margin: 0; line-height: 1.5; }
.point-tag { font-size: 10px; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 2px !important; }
.point-tag.ok { color: var(--ok); }
.point-tag.low { color: var(--low); }
.point-tag.info { color: var(--mid); }
.point .dot.low { background: var(--low); }

.cta { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); }
.cta-title { margin: 0; font-size: 13.5px; font-weight: 600; }
.cta-desc { margin: 3px 0 0; font-size: 11.5px; color: var(--muted); }
.btn-amber { border: 0; border-radius: 8px; padding: 10px 18px; background: var(--amber); color: #1a1100; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.btn-amber:hover { filter: brightness(0.95); }

/* Tabel */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { padding: 12px 0; text-align: left; font-size: 11px; font-weight: 500; color: var(--muted); border-bottom: 1px solid var(--line); }
td { padding: 14px 0; border-bottom: 1px solid #f0f2f6; white-space: nowrap; font-size: 12px; }
tbody tr:last-child td { border-bottom: 0; }
.badge { display: inline-block; padding: 2px 9px; border-radius: 6px; font-size: 10px; font-weight: 600; }
.badge.naik { background: #dff5ea; color: #13804f; }
.badge.turun { background: #fde4e4; color: #c7383d; }
.badge.stabil { background: #eef1f6; color: #5a6578; }

@media (max-width: 860px) {
  .grid-2 { grid-template-columns: 1fr; }
  .bar-head { flex-direction: column; align-items: flex-start; gap: 8px; }
  .bar-row { grid-template-columns: 70px 1fr 36px; }
  .bar-label { display: none; }
  .cta { flex-direction: column; align-items: flex-start; }
}
</style>