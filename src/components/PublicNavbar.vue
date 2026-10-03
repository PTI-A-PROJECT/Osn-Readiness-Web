<script setup>
import { RouterLink } from 'vue-router'

// Satu-satunya navbar untuk halaman publik (landing, login, register).
// `links`: tautan jangkar SEHALAMAN (mis. section di landing). Kosongkan di
// halaman yang tidak punya section — menu otomatis disembunyikan agar tidak
// ada dead link. `current`: halaman aktif, CTA-nya disembunyikan agar tidak
// ada tautan ke diri sendiri.
defineProps({
  links: { type: Array, default: () => [] },
  current: { type: String, default: 'landing' }, // landing | login | register
})
</script>

<template>
  <header class="pubnav">
    <div class="pubnav__inner">
      <RouterLink to="/" class="pubnav__brand" aria-label="SIAP OSN — ke beranda">
        <span class="pubnav__logo" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 5.5C2 4.7 2.7 4 3.5 4H10a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H3.5A1.5 1.5 0 0 1 2 15.5v-10Z" />
            <path d="M22 5.5c0-.8-.7-1.5-1.5-1.5H14a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h6.5a1.5 1.5 0 0 0 1.5-1.5v-10Z" />
          </svg>
        </span>
        <span class="pubnav__name">SIAP OSN</span>
      </RouterLink>

      <nav v-if="links.length" class="pubnav__links" aria-label="Navigasi utama">
        <a v-for="l in links" :key="l.href" :href="l.href">{{ l.label }}</a>
      </nav>

      <div class="pubnav__actions">
        <RouterLink v-if="current !== 'login'" to="/login" class="pubnav__btn pubnav__btn--outline">Masuk</RouterLink>
        <RouterLink v-if="current !== 'register'" to="/register" class="pubnav__btn pubnav__btn--primary">Daftar Gratis</RouterLink>
      </div>
    </div>
  </header>
</template>

<style scoped>
.pubnav {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  position: sticky;
  top: 0;
  z-index: 40;
  font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
.pubnav__inner {
  max-width: 1160px;
  margin: 0 auto;
  min-height: 62px;
  padding: 8px 24px;
  display: flex;
  align-items: center;
  gap: 20px;
}
.pubnav__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #0f1b3d;
}
.pubnav__logo {
  width: 30px;
  height: 26px;
  border-radius: 8px;
  background: #2f6fe4;
  display: grid;
  place-items: center;
}
.pubnav__name {
  font-weight: 700;
  font-size: 14px;
  white-space: nowrap;
}
.pubnav__links {
  display: flex;
  align-items: center;
  gap: 24px;
  margin: 0 auto;
}
.pubnav__links a {
  font-size: 13px;
  color: #334155;
  text-decoration: none;
  white-space: nowrap;
}
.pubnav__links a:hover {
  color: #1e4b8f;
}
.pubnav__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}
.pubnav__btn {
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid transparent;
  white-space: nowrap;
}
.pubnav__btn--outline {
  background: #fff;
  color: #0f1b3d;
  border-color: #e2e8f0;
}
.pubnav__btn--outline:hover {
  border-color: #1e4b8f;
  color: #1e4b8f;
}
.pubnav__btn--primary {
  background: #1e4b8f;
  color: #fff;
}
.pubnav__btn--primary:hover {
  background: #183d75;
}
.pubnav__brand:focus-visible,
.pubnav__links a:focus-visible,
.pubnav__btn:focus-visible {
  outline: 2px solid #1e4b8f;
  outline-offset: 2px;
  border-radius: 4px;
}

@media (max-width: 900px) {
  .pubnav__links {
    display: none;
  }
  .pubnav__inner {
    justify-content: space-between;
  }
}
</style>
