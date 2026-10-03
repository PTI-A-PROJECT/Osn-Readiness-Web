<script setup>
import PublicNavbar from '@/components/PublicNavbar.vue'

const navLinks = [
  { label: 'Kenapa SIAP OSN', href: '#kenapa' },
  { label: 'Cara Kerja', href: '#cara-kerja' },
  { label: 'Fitur', href: '#fitur' },
  { label: 'Tingkat Seleksi', href: '#tingkat' },
  { label: 'Tim Kami', href: '#tim' },
]

const stats = [
  { value: '3', label: 'Tingkat seleksi' },
  { value: '6', label: 'Kelompok fitur belajar' },
  { value: '100%', label: 'Materi dipersonalisasi' },
]

const competencies = [
  { name: 'Struktur Data', value: 82, color: 'blue' },
  { name: 'Algoritma Greedy', value: 54, color: 'blue' },
  { name: 'Graf & Pohon', value: 38, color: 'yellow' },
  { name: 'Dynamic Programming', value: 29, color: 'yellow' },
]

const priorities = [
  { icon: '📊', judul: 'Dynamic Programming Dasar', sub: 'Prioritas tinggi · Belum dikuasai', tone: 'yellow' },
  { icon: '🌳', judul: 'Graf & Pohon Lanjutan', sub: 'Prioritas sedang · Perlu latihan', tone: 'blue' },
  { icon: '✅', judul: 'Struktur Data Dasar', sub: 'Dikuasai · Simulasi tersedia', tone: 'green' },
]

const steps = [
  {
    title: 'Daftar',
    text: 'Buat akun dengan nama, email, dan password dalam hitungan detik.',
  },
  {
    title: 'Pilih Tingkat',
    text: 'Tentukan tingkat seleksi yang sedang kamu ikuti: Kabupaten, Provinsi, atau Nasional.',
  },
  {
    title: 'Kerjakan Pre-Test',
    text: 'Sistem mengukur kompetensi awalmu dan memetakan area yang perlu ditingkatkan.',
  },
  {
    title: 'Belajar & Simulasi',
    text: 'Ikuti materi yang direkomendasikan, lalu uji kesiapan lewat simulasi seleksi.',
  },
]

const features = [
  {
    icon: '📝',
    title: 'Pre-test Adaptif',
    text: 'Mengukur kemampuan awal sesuai tingkat seleksi yang kamu pilih, sebelum masuk ke materi.',
  },
  {
    icon: '🧭',
    title: 'Pemetaan Kompetensi Otomatis',
    text: 'Sistem menganalisis hasil pre-test dan menunjukkan kompetensi mana yang sudah kuat dan yang masih perlu ditingkatkan.',
    dark: true,
  },
  {
    icon: '🎯',
    title: 'Rekomendasi Materi Personalisasi',
    text: 'Belajar difokuskan pada materi yang paling kamu butuhkan, bukan menghabiskan waktu untuk topik yang sudah dikuasai.',
  },
  {
    icon: '🏁',
    title: 'Simulasi Seleksi Bertingkat',
    text: 'Rasakan simulasi ujian yang merepresentasikan kondisi seleksi sesungguhnya di tiap tingkat.',
  },
  {
    icon: '💡',
    title: 'Pembahasan & Evaluasi Tiap Soal',
    text: 'Setiap soal dilengkapi pembahasan agar kamu tahu persis letak kesalahan dan cara memperbaikinya.',
    dark: true,
  },
  {
    icon: '📈',
    title: 'Dashboard Progress Belajar',
    text: 'Pantau perkembangan belajar, riwayat pre-test, latihan, dan simulasi dalam satu tempat.',
  },
]

const levels = [
  { title: 'Kabupaten', text: 'Titik awal persiapan — membangun fondasi kompetensi dasar Informatika.' },
  { title: 'Provinsi', text: 'Materi dan simulasi meningkat mengikuti standar seleksi tingkat provinsi.' },
]

const footerColumns = [
  {
    title: 'Produk',
    links: [
      { label: 'Fitur', href: '#fitur' },
      { label: 'Cara Kerja', href: '#cara-kerja' },
      { label: 'Tingkat Seleksi', href: '#tingkat' },
    ],
  },
  {
    title: 'Tentang',
    links: [
      { label: 'Tim Kami', href: '#tim' },
      { label: 'Kenapa SIAP OSN', href: '#kenapa' },
    ],
  },
  {
    title: 'Mulai',
    links: [
      { label: 'Daftar Gratis', href: '/register' },
      { label: 'Masuk', href: '/login' },
    ],
  },
]
</script>

<template>
  <div class="lp">
    <!-- Navbar -->
    <PublicNavbar :links="navLinks" current="landing" theme="dark" />

    <!-- Hero -->
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

        <div class="map-card" role="img" aria-label="Contoh pemetaan kompetensi tingkat Provinsi">
          <div class="map-card__head">
            <strong>Pemetaan Kompetensi</strong>
            <span class="map-card__user">
              <span class="map-card__avatar" aria-hidden="true"></span>
              <span class="pill pill--muted">Tingkat Provinsi</span>
            </span>
          </div>
          <ul class="bars">
            <li v-for="c in competencies" :key="c.name">
              <span class="bars__name">{{ c.name }}</span>
              <span class="bars__track">
                <span class="bars__fill" :class="`bars__fill--${c.color}`" :style="{ width: c.value + '%' }"></span>
              </span>
              <span class="bars__value">{{ c.value }}%</span>
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

    <!-- Masalah -->
    <section id="kenapa" class="section section--white">
      <div class="container split">
        <div class="split__text">
          <h2>Kenapa persiapan OSN itu <span class="hl">sulit?</span></h2>
          <p>
            Jenjang seleksi bertahap (Kabupaten → Provinsi → Nasional), materi yang luas, dan
            minimnya simulasi realistis membuat siswa sulit tahu harus fokus belajar dari mana.
          </p>
          <a href="#solusi" class="btn btn--ghost">Lihat solusinya</a>
        </div>
        <div class="network" aria-hidden="true">
          <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
            <g stroke="#B7C0D6" stroke-width="1.2">
              <line x1="200" y1="150" x2="90" y2="75" />
              <line x1="200" y1="150" x2="320" y2="65" />
              <line x1="200" y1="150" x2="95" y2="230" />
              <line x1="200" y1="150" x2="210" y2="245" />
              <line x1="200" y1="150" x2="320" y2="235" />
            </g>
            <circle cx="90" cy="75" r="16" fill="#4F86F0" />
            <circle cx="320" cy="65" r="12" fill="#fff" />
            <circle cx="95" cy="230" r="12" fill="#7FB0F5" />
            <circle cx="210" cy="245" r="9" fill="#fff" />
            <circle cx="320" cy="235" r="15" fill="#3B76E8" />
            <circle cx="200" cy="150" r="26" fill="#12284F" />
            <text x="200" y="154" text-anchor="middle" fill="#fff" font-size="10" font-weight="600">Siswa</text>
          </svg>
        </div>
      </div>
    </section>

    <!-- Solusi -->
    <section id="solusi" class="section section--navy">
      <div class="container split split--reverse">
        <div class="priority-card">
          <h3 class="priority-card__title">Rekomendasi Materi Kamu</h3>
          <span class="tag tag--green">Diperbarui otomatis</span>
          <ul>
            <li v-for="p in priorities" :key="p.judul">
              <span class="rec-icon" :class="`dot--${p.tone}`" aria-hidden="true">{{ p.icon }}</span>
              <span>
                <strong>{{ p.judul }}</strong>
                <small>{{ p.sub }}</small>
              </span>
            </li>
          </ul>
        </div>
        <div class="split__text split__text--light">
          <small class="kicker">Solusinya</small>
          <h2>Bagaimana SIAP OSN <span class="hl">menjawab masalah itu?</span></h2>
          <p>
            SIAP OSN memetakan kompetensi siswa secara otomatis lewat pre-test, memberi rekomendasi
            materi yang personal, dan menyediakan simulasi seleksi bertingkat lengkap dengan
            pembahasan — sehingga belajar jadi lebih terarah dan siap menghadapi ujian
            sesungguhnya.
          </p>
          <a href="/register" class="btn btn--light">Coba Pre-Test Sekarang</a>
        </div>
      </div>
    </section>

    <!-- Cara kerja -->
    <section id="cara-kerja" class="section section--navy-2">
      <div class="container">
        <small class="kicker">Alur belajar</small>
        <h2 class="light">How it works</h2>
        <p class="light-muted">
          Empat langkah sederhana dari pendaftaran sampai siap menghadapi seleksi sesungguhnya.
        </p>
        <ol class="steps">
          <li v-for="(s, i) in steps" :key="s.title">
            <span class="steps__no">{{ String(i + 1).padStart(2, '0') }}</span>
            <strong>{{ s.title }}</strong>
            <p>{{ s.text }}</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- Fitur -->
    <section id="fitur" class="section section--white">
      <div class="container">
        <h2 class="marked--block">Semua yang kamu butuhkan untuk <span class="hl">satu jalur persiapan</span></h2>
        <div class="features">
          <article v-for="f in features" :key="f.title" class="feature" :class="{ 'feature--dark': f.dark }">
            <span class="feature__icon" aria-hidden="true">{{ f.icon }}</span>
            <h3>{{ f.title }}</h3>
            <p>{{ f.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Tingkat seleksi -->
    <section id="tingkat" class="section section--soft">
      <div class="container levels">
        <small class="kicker kicker--center">Jenjang seleksi</small>
        <h2 class="marked--center">Naik tingkat sesuai <span class="hl">pencapaianmu</span></h2>
        <p class="levels__lead">
          Tingkat berikutnya terbuka otomatis setelah kamu memenuhi ketentuan pada tingkat
          sebelumnya.
        </p>
        <div class="levels__row">
          <template v-for="l in levels" :key="l.title">
            <article class="level">
              <h3>{{ l.title }}</h3>
              <p>{{ l.text }}</p>
            </article>
            <span class="levels__arrow" aria-hidden="true">→</span>
          </template>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="section section--navy cta">
      <div class="container cta__inner">
        <h2 class="light">Siap hadapi seleksi OSN Informatika?</h2>
        <p class="light-muted">Mulai dengan pre-test gratis dan lihat langsung peta kompetensimu.</p>
        <div class="cta__buttons">
          <a href="/register" class="btn btn--light">Daftar Gratis</a>
          <a href="/login" class="btn btn--outline">Sudah punya akun? Masuk</a>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer id="tim" class="footer">
      <div class="container footer__grid">
        <div class="footer__about">
          <a href="#" class="brand" aria-label="SIAP OSN">
            <img class="brand__logo" src="/logo-icon.png" alt="" aria-hidden="true" />
            <span>SIAP OSN</span>
          </a>
          <p>
            Sistem Interaktif Adaptif Persiapan OSN — membantu siswa mempersiapkan diri menghadapi
            seleksi OSN Bidang Informatika secara adaptif.
          </p>
        </div>
        <div v-for="col in footerColumns" :key="col.title" class="footer__col">
          <strong>{{ col.title }}</strong>
          <a v-for="l in col.links" :key="l.label" :href="l.href">{{ l.label }}</a>
        </div>
      </div>
      <div class="container footer__bottom">
        <span>© 2026 SIAP OSN. Seluruh hak cipta dilindungi.</span>
        <span>Malang, Indonesia</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.lp {
  --navy: #0a1d3d;
  --navy-2: #0e2a52;
  --navy-3: #12284f;
  --ink: #0f1b33;
  --muted: #5b6784;
  --soft: #edf0f8;
  --line: #e3e7f0;
  --blue: #3b82f6;
  --yellow: #f5c96a;
  --green: #38b27a;
  --radius: 10px;

  font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  color: var(--ink);
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
}
.lp *,
.lp *::before,
.lp *::after {
  box-sizing: border-box;
}
.lp h1,
.lp h2,
.lp h3 {
  font-family: 'Space Grotesk', 'Inter', system-ui, sans-serif;
  line-height: 1.15;
  margin: 0;
  letter-spacing: -0.01em;
}
.lp p {
  margin: 0;
}
.lp a:not(.btn) {
  color: inherit;
  text-decoration: none;
}
.container {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s, border-color 0.15s;
}
.btn--sm {
  padding: 9px 16px;
}
.btn--xs {
  padding: 8px 16px;
  font-size: 0.8rem;
}
.btn--light {
  background: #fff;
  color: var(--navy);
}
.btn--light:hover {
  background: #e8edf8;
}
.btn--outline {
  border-color: rgba(255, 255, 255, 0.45);
  color: #fff;
}
.btn--outline:hover {
  border-color: #fff;
  background: rgba(255, 255, 255, 0.08);
}
.btn--ghost {
  border-color: var(--line);
  color: var(--ink);
  background: #fff;
}
.btn--ghost:hover {
  border-color: #c5cbe0;
}
.btn:focus-visible {
  outline: 2px solid var(--yellow);
  outline-offset: 2px;
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
}

/* Hero */
.hero {
  background: linear-gradient(180deg, var(--navy) 0%, #0b2245 100%);
  color: #fff;
  padding: 56px 0 72px;
}
.hero__grid {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: 48px;
  align-items: center;
}
.hero h1 {
  font-size: clamp(2rem, 4.2vw, 2.9rem);
  font-weight: 700;
  margin: 18px 0 16px;
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
  font-size: 0.88rem;
  margin-bottom: 18px;
}
.map-card__user {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.map-card__avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: conic-gradient(from 200deg, #4b8df8, #f5c96a, #38b27a, #4b8df8);
}
.bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 14px;
}
.bars li {
  display: grid;
  grid-template-columns: 130px 1fr 36px;
  align-items: center;
  gap: 12px;
  font-size: 0.78rem;
  color: #d6def0;
}
.bars__track {
  height: 6px;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 99px;
  overflow: hidden;
}
.bars__fill {
  display: block;
  height: 100%;
  border-radius: 99px;
}
.bars__fill--blue {
  background: #4b8df8;
}
.bars__fill--yellow {
  background: var(--yellow);
}
.bars__value {
  text-align: right;
}
.map-card__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 22px;
  padding-top: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}
.map-card__foot small {
  display: block;
  font-size: 0.68rem;
  color: #9fb0d3;
}
.map-card__foot strong {
  font-size: 0.85rem;
}

/* Sections */
.section {
  padding: 80px 0;
}
.section--white {
  background: #fff;
}
.section--navy {
  background: var(--navy);
  color: #fff;
}
.section--navy-2 {
  background: var(--navy-2);
  color: #fff;
}
.section--soft {
  background: var(--soft);
}

.split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 56px;
  align-items: center;
}
.split__text {
  display: grid;
  gap: 20px;
  justify-items: start;
}
.split__text h2 {
  font-size: clamp(1.6rem, 3vw, 2.1rem);
}
.split__text p {
  color: var(--muted);
  font-size: 0.92rem;
  max-width: 46ch;
}
.split__text--light p {
  color: #c8d2ea;
}
.kicker {
  font-size: 0.78rem;
  color: #c8d2ea;
}
.kicker--dark {
  color: var(--ink);
  font-weight: 600;
}
.kicker--center {
  display: block;
  text-align: center;
  margin-bottom: 14px;
}
.levels .marked--center {
  margin-top: 14px;
}

/* Sorotan spidol kuning di belakang potongan judul (persis Figma) */
.hl {
  background: linear-gradient(transparent 58%, var(--yellow) 58%, var(--yellow) 94%, transparent 94%);
}
.marked--block {
  display: block;
  max-width: 14em;
  font-size: clamp(1.6rem, 3vw, 2.1rem);
  margin-top: 14px;
  margin-bottom: 64px;
}
.marked--center {
  display: block;
  text-align: center;
  font-size: clamp(1.6rem, 3vw, 2.1rem);
}

.network {
  background: var(--soft);
  border-radius: 16px;
  padding: 28px;
}
.network svg {
  width: 100%;
  height: auto;
  display: block;
}

.priority-card {
  background: #fff;
  color: var(--ink);
  border-radius: 14px;
  padding: 20px 24px 8px;
}
.priority-card__title {
  font-size: 0.95rem;
  margin: 0 0 4px;
}
.priority-card ul {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
}
.priority-card li {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 0;
  border-top: 1px solid var(--line);
  font-size: 0.8rem;
  color: var(--muted);
}
.priority-card li strong {
  display: block;
  color: var(--ink);
  font-size: 0.85rem;
}
.priority-card li small {
  display: block;
  font-size: 0.72rem;
}
.tag {
  display: block;
  width: fit-content;
  margin-left: auto;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 0.68rem;
  font-weight: 600;
}
.tag--green {
  background: #e3f5ec;
  color: #1f8a5b;
}
.rec-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  flex: none;
  display: grid;
  place-items: center;
  font-size: 1rem;
}
.dot--yellow {
  background: #fdf0d5;
}
.dot--blue {
  background: #e3ecfd;
}
.dot--green {
  background: #dff3e8;
}

/* Cara kerja */
.light {
  color: #fff;
  font-size: clamp(1.6rem, 3vw, 2.1rem);
  margin: 8px 0 10px;
}
.light-muted {
  color: #c8d2ea;
  font-size: 0.92rem;
  max-width: 44ch;
}
.steps {
  list-style: none;
  margin: 40px 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.steps li {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: var(--radius);
  padding: 22px 20px;
}
.steps__no {
  display: block;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.4rem;
  font-weight: 600;
  margin-bottom: 22px;
}
.steps strong {
  display: block;
  font-size: 0.9rem;
  margin-bottom: 6px;
}
.steps p {
  font-size: 0.78rem;
  color: #9fb0d3;
}

/* Fitur */
.features {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
.feature {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 22px;
}
.feature__icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: var(--soft);
  margin-bottom: 26px;
  font-size: 1.2rem;
}
.feature h3 {
  font-size: 0.92rem;
  margin-bottom: 8px;
}
.feature p {
  font-size: 0.8rem;
  color: var(--muted);
}
.feature--dark {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}
.feature--dark .feature__icon {
  background: rgba(255, 255, 255, 0.1);
}
.feature--dark p {
  color: #b7c4e2;
}

/* Tingkat */
.levels__lead {
  text-align: center;
  color: var(--muted);
  font-size: 0.92rem;
  max-width: 46ch;
  margin: 16px auto 64px;
}
.levels__row {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto;
  align-items: center;
  gap: 12px;
}
.level {
  background: #fff;
  border-radius: var(--radius);
  padding: 24px;
  height: 100%;
}
.level h3 {
  font-size: 1rem;
  margin-bottom: 8px;
}
.level p {
  font-size: 0.8rem;
  color: var(--muted);
}
.levels__arrow {
  color: #98a3bf;
  font-size: 1.4rem;
}

/* CTA */
.cta {
  padding: 72px 0;
}
.cta__inner {
  text-align: center;
  display: grid;
  justify-items: center;
  gap: 8px;
}
.cta__buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
}

/* Footer */
.footer {
  background: var(--navy);
  color: #c8d2ea;
  padding: 56px 0 28px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}
.footer__grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1fr;
  gap: 32px;
}
.footer .brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
}
.footer .brand__logo {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  object-fit: cover;
}
.footer__about p {
  font-size: 0.78rem;
  margin-top: 12px;
  max-width: 34ch;
}
.footer__col {
  display: grid;
  gap: 10px;
  align-content: start;
  font-size: 0.8rem;
}
.footer__col strong {
  color: #fff;
  font-size: 0.82rem;
}
.footer__col a:hover {
  color: #fff;
}
.footer__bottom {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 48px;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  font-size: 0.74rem;
  color: #8fa0c6;
}

/* Responsif */
@media (max-width: 900px) {
  .hero__grid,
  .split,
  .steps,
  .features,
  .footer__grid {
    grid-template-columns: 1fr;
  }
  .split--reverse .priority-card {
    order: 2;
  }
  .levels__row {
    grid-template-columns: 1fr;
  }
  .levels__arrow {
    display: none;
  }
  .bars li {
    grid-template-columns: 110px 1fr 36px;
  }
}
@media (max-width: 520px) {
  .stats {
    gap: 24px;
  }
  .footer__bottom {
    flex-direction: column;
  }
}
</style>
