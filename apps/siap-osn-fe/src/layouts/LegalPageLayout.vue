<script setup>
import { RouterLink } from 'vue-router'
import PublicNavbar from '@/components/PublicNavbar.vue'

defineProps({
  title: { type: String, required: true },
  intro: { type: String, required: true },
})
</script>

<template>
  <div class="legal-page">
    <PublicNavbar :links="[]" />
    <main class="legal-content">
      <RouterLink to="/" class="back-link">Kembali ke beranda</RouterLink>
      <header class="legal-heading">
        <p>SIAP OSN</p>
        <h1>{{ title }}</h1>
        <span>{{ intro }}</span>
      </header>
      <div class="legal-body"><slot /></div>
    </main>
    <footer class="legal-footer">
      <span>© 2026 SIAP OSN</span>
      <nav aria-label="Dokumen layanan">
        <RouterLink to="/kebijakan-privasi">Kebijakan Privasi</RouterLink>
        <RouterLink to="/ketentuan-layanan">Ketentuan Layanan</RouterLink>
      </nav>
    </footer>
  </div>
</template>

<style scoped>
.legal-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f7f9fc;
  color: #17233b;
  font-family: 'Inter', system-ui, sans-serif;
}
.legal-content {
  width: min(100% - 40px, 760px);
  margin: 0 auto;
  padding: 40px 0 72px;
  flex: 1;
}
.back-link {
  color: #0759a5;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
}
.back-link:hover, .legal-body :deep(a:hover), .legal-footer a:hover { text-decoration: underline; }
.legal-heading {
  padding: 44px 0 28px;
  border-bottom: 1px solid #dce4ef;
}
.legal-heading p {
  margin: 0 0 14px;
  color: #315f98;
  font-size: 13px;
  font-weight: 700;
}
.legal-heading h1 {
  margin: 0 0 16px;
  font-size: clamp(30px, 5vw, 46px);
  letter-spacing: -.03em;
  line-height: 1.12;
}
.legal-heading span { color: #526178; line-height: 1.65; }
.legal-body { font-size: 15px; line-height: 1.75; }
.legal-body :deep(section) { padding-top: 25px; }
.legal-body :deep(h2) { margin: 0 0 10px; font-size: 21px; line-height: 1.3; }
.legal-body :deep(p) { margin: 0 0 14px; }
.legal-body :deep(ul) { margin: 0 0 14px; padding-left: 22px; }
.legal-body :deep(li + li) { margin-top: 7px; }
.legal-body :deep(a) { color: #0759a5; }
.legal-footer {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 24px max(20px, calc((100vw - 760px) / 2));
  border-top: 1px solid #dce4ef;
  color: #526178;
  font-size: 13px;
}
.legal-footer nav { display: flex; gap: 20px; flex-wrap: wrap; }
.legal-footer a { color: #0759a5; text-decoration: none; }
.back-link:focus-visible, .legal-body :deep(a:focus-visible), .legal-footer a:focus-visible {
  outline: 2px solid #0759a5;
  outline-offset: 3px;
}
@media (max-width: 600px) {
  .legal-content { width: min(100% - 32px, 760px); padding-top: 28px; }
  .legal-heading { padding-top: 32px; }
  .legal-body :deep(h2) { font-size: 19px; }
}
</style>
