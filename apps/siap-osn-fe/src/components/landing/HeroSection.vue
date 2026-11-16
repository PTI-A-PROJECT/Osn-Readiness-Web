<script setup>
const stats = [
  { value: '3', label: 'Tingkat seleksi' },
  { value: '6', label: 'Kelompok fitur belajar' },
  { value: '100%', label: 'Materi dipersonalisasi' },
]

// Ilustrasi statis untuk memperlihatkan contoh hasil pemetaan kompetensi.
const competencies = [
  { name: 'Struktur Data', score: 82, tone: 'blue' },
  { name: 'Algoritma Greedy', score: 54, tone: 'blue' },
  { name: 'Graf & Pohon', score: 38, tone: 'yellow' },
  { name: 'Dynamic Programming', score: 29, tone: 'yellow' },
]
</script>

<template>
  <section class="hero">
    <div class="container hero__grid">
      <div class="hero__copy">
        <span class="pill">Persiapan OSN Bidang Informatika</span>
        <h1>Sistem Interaktif<br />Adaptif<br />Persiapan OSN</h1>
        <p class="hero__lead">
          Persiapkan dirimu menghadapi seleksi OSN Bidang Informatika secara adaptif — dari
          tingkat Kabupaten hingga Nasional — dengan pemetaan kompetensi otomatis dan materi yang
          dipersonalisasi.
        </p>
        <div class="hero__cta">
          <a href="/register" class="btn btn--light">Mulai Pre-Test Gratis</a>
          <a href="#cara-kerja" class="btn btn--outline">Lihat Cara Kerja</a>
        </div>
        <dl class="stats">
          <div v-for="s in stats" :key="s.label">
            <dt>{{ s.value }}</dt>
            <dd>{{ s.label }}</dd>
          </div>
        </dl>
      </div>

      <div class="map-card" aria-label="Contoh pemetaan kompetensi siswa">
        <div class="map-card__head">
          <strong>Pemetaan Kompetensi</strong>
          <span class="pill pill--muted">Tingkat Provinsi</span>
        </div>

        <ul class="bars">
          <li v-for="item in competencies" :key="item.name">
            <span class="bars__name">{{ item.name }}</span>
            <span class="bars__track">
              <span
                class="bars__fill"
                :class="`bars__fill--${item.tone}`"
                :style="{ width: `${item.score}%` }"
              ></span>
            </span>
            <span class="bars__value">{{ item.score }}%</span>
          </li>
        </ul>

        <div class="map-card__foot">
          <div>
            <small>Rekomendasi berikutnya</small>
            <strong>Latihan Dynamic Programming Dasar</strong>
          </div>
          <a href="/register" class="btn btn--light btn--xs">Belajar</a>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped src="./shared.css"></style>

<style scoped>
.hero {
  background: linear-gradient(180deg, var(--navy) 0%, #0b2245 100%);
  color: #fff;
  padding: 56px 0 72px;
}

.hero h1 {
  font-size: clamp(2rem, 4.2vw, 2.9rem);
  font-weight: 700;
  margin: 18px 0 16px;
}

.hero__grid {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: 48px;
  align-items: center;
}

.hero__lead {
  color: #c8d2ea;
  font-size: 0.95rem;
  max-width: 46ch;
}

.hero__cta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 28px 0 40px;
}

.stats {
  display: flex;
  gap: 40px;
  margin: 0;
}

.stats dt {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.4rem;
  font-weight: 700;
}

.stats dd {
  margin: 0;
  font-size: 0.75rem;
  color: #9fb0d3;
}

.map-card {
  background: var(--navy-3);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 22px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
}

.map-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  font-size: 0.88rem;
  margin-bottom: 22px;
}

.bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 18px;
}

.bars li {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr) 36px;
  align-items: center;
  gap: 12px;
  font-size: 0.78rem;
  color: #d6def0;
}

.bars__track {
  display: block;
  height: 6px;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 99px;
  overflow: hidden;
}

.bars__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
}

.bars__fill--blue {
  background: #60a5fa;
}

.bars__fill--yellow {
  background: #f5c542;
}

.bars__value {
  text-align: right;
  color: #9fb0d3;
  font-size: 0.7rem;
}

.map-card__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 26px;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.map-card__foot small {
  display: block;
  font-size: 0.68rem;
  color: #9fb0d3;
  margin-bottom: 4px;
}

.map-card__foot strong {
  display: block;
  font-size: 0.82rem;
}

.pill {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 500;
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
}

.pill--muted {
  background: rgba(255, 255, 255, 0.1);
  color: #d6def0;
  white-space: nowrap;
}

@media (max-width: 900px) {
  .hero__grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 520px) {
  .stats {
    gap: 24px;
  }

  .bars li {
    grid-template-columns: minmax(0, 1fr) 1fr 36px;
    gap: 8px;
    font-size: 0.7rem;
  }

  .map-card {
    padding: 18px;
  }
}
</style>
