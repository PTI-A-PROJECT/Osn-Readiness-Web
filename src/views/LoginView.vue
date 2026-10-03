<script setup>
import { ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import { useAuthStore } from '@/stores/auth.js'
import { dashboardFor } from '@/router/index.js'
import { pesanError } from '@/lib/errors.js'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import PublicNavbar from '@/components/PublicNavbar.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const toast = useToast()

const email = ref('')
const password = ref('')
const remember = ref(false)
const emailError = ref('')
const passwordError = ref('')
const loading = ref(false)

const highlights = [
  'Pre-test & pemetaan kompetensi',
  'Materi sesuai kebutuhanmu',
  'Simulasi seleksi standar TOKI',
]

function validasi() {
  emailError.value = ''
  passwordError.value = ''
  if (!email.value) emailError.value = 'Email wajib diisi'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) emailError.value = 'Format email tidak valid'
  if (!password.value) passwordError.value = 'Password wajib diisi'
  return !emailError.value && !passwordError.value
}

async function masuk() {
  if (!validasi()) return
  loading.value = true
  try {
    // "remember" belum dikirim; tambahkan ke payload kalau backend sudah mendukung
    const user = await auth.login({ email: email.value, password: password.value })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    router.push(redirect ?? dashboardFor(user.role))
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Login gagal', detail: pesanError(err), life: 4000 })
  } finally {
    loading.value = false
  }
}

function lupaPassword() {
  // Belum ada endpoint reset password di backend — jangan routing ke
  // halaman yang tidak ada (dulu RouterLink ke /forgot-password yang
  // tidak terdaftar dan jatuh ke not-found).
  toast.add({ severity: 'info', summary: 'Segera hadir', detail: 'Reset kata sandi belum tersedia.', life: 3000 })
}

function masukGoogle() {
  // TODO: sambungkan ke Google OAuth
  toast.add({ severity: 'info', summary: 'Segera hadir', detail: 'Login dengan Google belum tersedia.', life: 3000 })
}
</script>

<template>
  <div class="page">
    <PublicNavbar current="login" />

    <main class="content">
      <!-- KIRI -->
      <section class="left">
        <div class="glow glow--yellow"></div>
        <div class="glow glow--gray"></div>

        <div class="left__inner">
          <h1 class="hero__title">Selamat Datang<br />Kembali!</h1>
          <p class="hero__desc">
            Persiapan OSN Informatika yang terarah: dari pemetaan
            kompetensi sampai simulasi sesuai standar TOKI.
          </p>

          <ul class="highlights">
            <li v-for="item in highlights" :key="item" class="highlights__item">
              <span class="check">
                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
              </span>
              {{ item }}
            </li>
          </ul>
        </div>
      </section>

      <!-- KANAN -->
      <section class="right">
        <div class="card">
          <h2 class="card__title">Masuk ke Akun</h2>
          <p class="card__subtitle">
            Lanjutkan latihan soal, simulasi, dan pelajari materi kompetisi Informatika.
          </p>

          <form class="form" @submit.prevent="masuk">
            <div class="field">
              <label for="email" class="field__label">Alamat Email</label>
              <div class="field__wrap">
                <span class="field__icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="m3 7 9 6 9-6" />
                  </svg>
                </span>
                <InputText
                  id="email"
                  v-model="email"
                  placeholder="nama@sekolah.sch.id"
                  autocomplete="email"
                  fluid
                />
              </div>
              <small v-if="emailError" class="field__error">{{ emailError }}</small>
            </div>

            <div class="field">
              <div class="field__row">
                <label for="password" class="field__label">Kata Sandi</label>
                <a href="#" class="forgot" @click.prevent="lupaPassword">Lupa kata sandi?</a>
              </div>
              <div class="field__wrap">
                <span class="field__icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2" />
                    <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                  </svg>
                </span>
                <Password
                  v-model="password"
                  input-id="password"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  :feedback="false"
                  toggle-mask
                  fluid
                />
              </div>
              <small v-if="passwordError" class="field__error">{{ passwordError }}</small>
            </div>

            <label class="remember">
              <input v-model="remember" type="checkbox" />
              <span>Ingat saya di perangkat ini</span>
            </label>

            <Button type="submit" label="Masuk →" :loading="loading" class="login-btn p-button" />
          </form>

          <div class="divider"><span>atau masuk dengan</span></div>

          <Button type="button" class="google-btn p-button" @click="masukGoogle">
            <svg width="16" height="16" viewBox="0 0 48 48">
              <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z" />
              <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z" />
              <path fill="#FBBC05" d="M10.5 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.8l7.9-6.1z" />
              <path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z" />
            </svg>
            <span>Masuk dengan Google</span>
          </Button>

          <p class="card__footer">
            Belum memiliki akun?
            <RouterLink to="/register" class="link">Daftar akun siswa</RouterLink>
          </p>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap');

.page {
  --navy: #0f1b3d;
  --blue: #1e4b8f;
  --blue-hover: #183d75;
  --border: #e2e8f0;
  --muted: #64748b;

  /* Menutup layout pembungkus supaya halaman tampil penuh (full-page) */
  position: fixed;
  inset: 0;
  z-index: 50;
  overflow-y: auto;
  color-scheme: light;
  display: flex;
  flex-direction: column;
  font-family: 'Inter', system-ui, sans-serif;
  color: var(--navy);
  background: #fff;
}

/* ---------- Layout ---------- */
.content {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ---------- Kiri ---------- */
.left {
  position: relative;
  overflow: hidden;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(60px);
}
.glow--yellow {
  width: 420px;
  height: 420px;
  top: 12%;
  right: 8%;
  background: radial-gradient(circle, rgba(254, 240, 200, 0.7), rgba(254, 240, 200, 0) 70%);
}
.glow--gray {
  width: 380px;
  height: 380px;
  bottom: -120px;
  right: -40px;
  background: radial-gradient(circle, rgba(203, 213, 225, 0.55), rgba(203, 213, 225, 0) 70%);
}
.left__inner {
  position: relative;
  width: 100%;
  max-width: 480px;
  padding: 48px 24px;
}
.hero__title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 44px;
  line-height: 1.25;
  font-weight: 700;
  margin: 0 0 20px;
}
.hero__desc {
  font-size: 15px;
  line-height: 1.65;
  color: #1e3a6b;
  margin: 0 0 28px;
  max-width: 380px;
}
.highlights {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 335px;
}
.highlights__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  background: #eef2f7;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
}
.check {
  flex: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--navy);
  display: grid;
  place-items: center;
}

/* ---------- Kanan / Kartu ---------- */
.right {
  background: #eef2f7;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
}
.card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 20px;
  padding: 34px 32px 30px;
  box-shadow: 0 12px 30px rgba(15, 27, 61, 0.08);
}
.card__title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 6px;
}
.card__subtitle {
  font-size: 13px;
  line-height: 1.5;
  color: var(--muted);
  margin: 0 0 24px;
}
.card__footer {
  text-align: center;
  font-size: 13px;
  color: var(--muted);
  margin: 18px 0 0;
}
.link {
  color: var(--navy);
  font-weight: 700;
  text-decoration: none;
}
.link:hover {
  color: var(--blue);
  text-decoration: underline;
}

/* ---------- Form ---------- */
.form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.forgot {
  font-size: 12px;
  font-weight: 600;
  color: var(--blue);
  text-decoration: none;
  cursor: pointer;
}
.forgot:hover {
  text-decoration: underline;
}

/* Field (label + input PrimeVue) */
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.field__label {
  font-size: 13px;
  font-weight: 600;
  color: var(--navy);
}
.field__wrap {
  position: relative;
}
.field__icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  color: #94a3b8;
  pointer-events: none;
  z-index: 1;
}
.field__error {
  font-size: 11px;
  color: #ef4444;
}
.field :deep(.p-inputtext) {
  height: 46px;
  padding-left: 2.4rem;
  border-radius: 10px;
  font-size: 13.5px;
  background: #fff;
  color: var(--navy);
  border-color: var(--border);
}
.field :deep(.p-inputtext::placeholder) {
  color: #a0aec0;
}
.field :deep(.p-inputtext:enabled:focus) {
  background: #fff;
}
.field :deep(.p-inputtext:enabled:focus) {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(30, 75, 143, 0.12);
}
.remember {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
}
.remember input {
  width: 14px;
  height: 14px;
  accent-color: var(--blue);
  cursor: pointer;
}

/* Override tampilan PrimeVue Button */
.login-btn.p-button {
  width: 100%;
  justify-content: center;
  padding: 15px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  background: var(--blue);
  border: 1px solid var(--blue);
  color: #fff;
}
.login-btn.p-button:not(:disabled):hover {
  background: var(--blue-hover);
  border-color: var(--blue-hover);
}
.google-btn.p-button {
  width: 100%;
  justify-content: center;
  gap: 8px;
  padding: 15px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  background: #f1f4f8;
  border: 1px solid var(--border);
  color: var(--navy);
}
.google-btn.p-button:not(:disabled):hover {
  background: #e8edf3;
}

.divider {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 20px 0;
  font-size: 11.5px;
  color: #94a3b8;
}
.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* Cegah teks tombol turun baris */
.google-btn.p-button,
.login-btn.p-button {
  white-space: nowrap;
}

/* ---------- Responsif ---------- */
@media (max-width: 900px) {
  .content {
    grid-template-columns: 1fr;
  }
  .left__inner {
    margin: 0 auto;
    padding: 40px 24px 16px;
  }
  .hero__title {
    font-size: 34px;
  }
}
</style>
