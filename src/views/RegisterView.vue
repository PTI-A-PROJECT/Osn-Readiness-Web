<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth.js'
import { pesanError } from '@/lib/errors.js'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()

const nama = ref('')
const email = ref('')
const password = ref('')
const konfirmasi = ref('')
const setuju = ref(false)
const namaError = ref('')
const emailError = ref('')
const passwordError = ref('')
const konfirmasiError = ref('')
const setujuError = ref('')
const loading = ref(false)

function validasi() {
  namaError.value = ''
  emailError.value = ''
  passwordError.value = ''
  konfirmasiError.value = ''
  setujuError.value = ''
  if (nama.value.trim().length < 3) namaError.value = 'Nama minimal 3 karakter'
  if (!email.value) emailError.value = 'Email wajib diisi'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) emailError.value = 'Format email tidak valid'
  if (password.value.length < 8) passwordError.value = 'Password minimal 8 karakter'
  else if (!/[a-zA-Z]/.test(password.value) || !/[0-9]/.test(password.value)) passwordError.value = 'Password harus mengandung huruf dan angka'
  if (konfirmasi.value !== password.value) konfirmasiError.value = 'Konfirmasi password tidak sama'
  if (!setuju.value) setujuError.value = 'Persetujuan wajib dicentang'
  return !namaError.value && !emailError.value && !passwordError.value && !konfirmasiError.value && !setujuError.value
}

async function daftar() {
  if (!validasi()) return
  loading.value = true
  try {
    await auth.register({ nama: nama.value, email: email.value, password: password.value })
    toast.add({ severity: 'success', summary: 'Registrasi berhasil', detail: 'Silakan masuk dengan akun baru', life: 4000 })
    router.push('/login')
  } catch (err) {
    if (err?.response?.status === 400 && err?.response?.data?.message === 'Email sudah terdaftar') {
      emailError.value = 'Email sudah terdaftar'
    } else {
      toast.add({ severity: 'error', summary: 'Registrasi gagal', detail: pesanError(err), life: 4000 })
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="register-page">
    <header class="navbar">
      <div class="navbar-container">
        <RouterLink to="/login" class="brand-logo" aria-label="SIAP OSN, kembali ke masuk">
          <span class="brand-mark" aria-hidden="true">S</span>
          <span>SIAP OSN</span>
        </RouterLink>
        <nav class="nav-menu" aria-label="Navigasi utama">
          <a href="#keunggulan">Kenapa SIAP OSN</a>
          <a href="#keunggulan">Fitur</a>
          <a href="#register-form">Cara Kerja</a>
          <a href="#register-form">Tingkat Seleksi</a>
        </nav>
        <div class="nav-actions">
          <RouterLink to="/login" class="btn-login">Masuk</RouterLink>
          <a href="#register-form" class="btn-register">Daftar Gratis</a>
        </div>
      </div>
    </header>

    <main class="main-content">
      <section id="keunggulan" class="left-section">
        <div class="left-content">
          <p class="eyebrow">SIAPKAN DIRIMU UNTUK OSN</p>
          <h1>Selamat<br />Datang!</h1>
          <p class="description">
            Persiapan OSN Informatika yang terarah: dari pemetaan kompetensi sampai simulasi sesuai standar TOKI.
          </p>
          <ul class="feature-list">
            <li><span class="check-icon" aria-hidden="true">✓</span>Pre-test &amp; pemetaan kompetensi</li>
            <li><span class="check-icon" aria-hidden="true">✓</span>Materi sesuai kebutuhanmu</li>
            <li><span class="check-icon" aria-hidden="true">✓</span>Simulasi seleksi standar TOKI</li>
          </ul>
        </div>
      </section>

      <section id="register-form" class="right-section">
        <div class="register-card">
          <div class="card-heading">
            <p class="card-kicker">MULAI PERJALANANMU</p>
            <h2>Daftar Akun Baru</h2>
            <p class="card-description">Buat akun untuk mulai mempersiapkan diri.</p>
          </div>

          <form class="register-form" @submit.prevent="daftar">
            <div class="form-group">
              <label for="nama">Nama Lengkap <span>*</span></label>
              <input id="nama" v-model="nama" autocomplete="name" type="text" placeholder="Masukkan nama lengkap" :aria-invalid="Boolean(namaError)" :aria-describedby="namaError ? 'nama-error' : undefined" />
              <small v-if="namaError" id="nama-error" class="field-error">{{ namaError }}</small>
            </div>

            <div class="form-group">
              <label for="email">Email Aktif <span>*</span></label>
              <input id="email" v-model="email" autocomplete="email" type="email" placeholder="nama@sekolah.sch.id" :aria-invalid="Boolean(emailError)" :aria-describedby="emailError ? 'email-error' : undefined" />
              <small v-if="emailError" id="email-error" class="field-error">{{ emailError }}</small>
            </div>

            <div class="form-group">
              <label for="password">Kata Sandi <span>*</span></label>
              <input id="password" v-model="password" autocomplete="new-password" type="password" placeholder="Buat kata sandi" :aria-invalid="Boolean(passwordError)" :aria-describedby="passwordError ? 'password-error' : 'password-hint'" />
              <small v-if="passwordError" id="password-error" class="field-error">{{ passwordError }}</small>
              <small v-else id="password-hint" class="field-hint">Minimal 8 karakter, kombinasi huruf dan angka</small>
            </div>

            <div class="form-group">
              <label for="konfirmasi">Konfirmasi Kata Sandi <span>*</span></label>
              <input id="konfirmasi" v-model="konfirmasi" autocomplete="new-password" type="password" placeholder="Ulangi kata sandi Anda" :aria-invalid="Boolean(konfirmasiError)" :aria-describedby="konfirmasiError ? 'konfirmasi-error' : undefined" />
              <small v-if="konfirmasiError" id="konfirmasi-error" class="field-error">{{ konfirmasiError }}</small>
            </div>

            <div class="agreement">
              <input id="agreement" v-model="setuju" type="checkbox" :aria-invalid="Boolean(setujuError)" :aria-describedby="setujuError ? 'agreement-error' : undefined" />
              <label for="agreement">Saya menyetujui <a href="#agreement">Ketentuan Layanan</a> dan <a href="#agreement">Kebijakan Privasi</a> SIAP OSN</label>
            </div>
            <small v-if="setujuError" id="agreement-error" class="field-error agreement-error">{{ setujuError }}</small>

            <button class="register-button" type="submit" :disabled="loading">
              <span>{{ loading ? 'Memproses...' : 'Daftar Sekarang' }}</span>
              <span aria-hidden="true">→</span>
            </button>
          </form>

          <p class="login-text">Sudah punya akun? <RouterLink to="/login">Masuk di sini</RouterLink></p>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.register-page {
  --ink: #17233b;
  --muted: #68758a;
  --blue: #0759a5;
  --blue-deep: #064d91;
  --line: #dce3ec;
  min-height: 100vh;
  color: var(--ink);
  background: #fff;
  font-family: 'Avenir Next', Avenir, 'Segoe UI', sans-serif;
}

.navbar {
  position: sticky;
  z-index: 2;
  top: 0;
  height: 72px;
  border-bottom: 1px solid var(--line);
  background: rgb(255 255 255 / 96%);
}

.navbar-container {
  display: flex;
  align-items: center;
  width: min(1120px, 100% - 48px);
  height: 100%;
  margin: 0 auto;
}

.brand-logo {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 9px;
  color: var(--ink);
  font-size: 16px;
  font-weight: 800;
  text-decoration: none;
}

.brand-mark {
  display: grid;
  width: 28px;
  aspect-ratio: 1;
  place-items: center;
  border-radius: 8px;
  background: var(--blue);
  color: white;
  font-size: 15px;
}

.nav-menu,
.nav-actions {
  display: flex;
  align-items: center;
}

.nav-menu {
  gap: 23px;
  margin-left: 38px;
}

.nav-menu a {
  color: #536570;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
}

.nav-menu a:hover,
.login-text a:hover {
  color: var(--blue);
}

.nav-actions {
  gap: 9px;
  margin-left: auto;
}

.btn-login,
.btn-register {
  display: inline-flex;
  min-height: 40px;
  align-items: center;
  justify-content: center;
  padding: 0 15px;
  border: 1px solid var(--line);
  border-radius: 7px;
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
}

.btn-register {
  border-color: var(--blue);
  background: var(--blue);
  color: white;
}

.btn-register:hover,
.register-button:hover:not(:disabled) {
  background: var(--blue-deep);
}

.main-content {
  display: grid;
  min-height: calc(100vh - 72px);
  grid-template-columns: 1.05fr 0.95fr;
}

.left-section,
.right-section {
  display: flex;
  align-items: center;
}

.left-section {
  justify-content: flex-end;
  padding: 64px clamp(32px, 6vw, 88px) 64px 24px;
  background: radial-gradient(ellipse at 15% 90%, #e6f1fb 0, transparent 42%), #fff;
}

.left-content {
  width: min(100%, 480px);
  animation: enter 500ms ease-out both;
}

.eyebrow,
.card-kicker {
  margin: 0 0 14px;
  color: var(--blue);
  font-size: 11px;
  font-weight: 800;
}

.eyebrow {
  letter-spacing: 1px;
}

.left-content h1 {
  margin: 0 0 20px;
  color: #10294a;
  font-size: 52px;
  font-weight: 800;
  line-height: 1.03;
}

.description {
  max-width: 460px;
  margin: 0 0 30px;
  color: #154b86;
  font-size: 16px;
  line-height: 1.65;
}

.feature-list {
  display: grid;
  gap: 11px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.feature-list li {
  display: flex;
  min-height: 48px;
  align-items: center;
  gap: 12px;
  padding: 0 14px;
  border: 1px solid #dce7f1;
  border-radius: 7px;
  background: rgb(255 255 255 / 78%);
  color: #182f50;
  font-size: 13px;
  font-weight: 700;
}

.check-icon {
  display: grid;
  width: 22px;
  aspect-ratio: 1;
  flex-shrink: 0;
  place-items: center;
  border-radius: 50%;
  background: #e3eef8;
  color: var(--blue);
  font-size: 13px;
}

.right-section {
  justify-content: flex-start;
  padding: 42px clamp(28px, 5.4vw, 78px);
  background: #f0f5fb;
}

.register-card {
  width: min(100%, 440px);
  margin: 0 auto;
  padding: 30px;
  border: 1px solid #dce4ed;
  border-radius: 9px;
  background: white;
  box-shadow: 0 12px 35px rgb(21 48 80 / 7%);
  animation: enter 600ms 80ms ease-out both;
}

.card-heading {
  margin-bottom: 23px;
}

.card-kicker {
  margin-bottom: 8px;
  letter-spacing: 0.7px;
}

.card-heading h2 {
  margin: 0;
  color: #172033;
  font-size: 22px;
  font-weight: 800;
}

.card-description {
  margin: 7px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.register-form {
  display: grid;
  gap: 13px;
}

.form-group {
  display: grid;
  gap: 6px;
}

.form-group label {
  color: #182238;
  font-size: 12px;
  font-weight: 750;
}

.form-group label span {
  color: #c54c45;
}

.form-group input {
  width: 100%;
  min-height: 42px;
  padding: 0 12px;
  border: 1px solid #d6e0eb;
  border-radius: 6px;
  outline: none;
  background: #fff;
  color: var(--ink);
  font: inherit;
  font-size: 13px;
}

.form-group input::placeholder {
  color: #95a5aa;
}

.form-group input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgb(11 104 189 / 11%);
}

.form-group input[aria-invalid='true'] {
  border-color: #c54c45;
}

.field-hint,
.field-error {
  font-size: 11px;
}

.field-hint {
  color: var(--muted);
}

.field-error {
  color: #b33a35;
}

.agreement {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin-top: 3px;
}

.agreement input {
  position: relative;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  margin: 1px 0 0;
  appearance: none;
  border: 1px solid #aebbd0;
  border-radius: 3px;
  background: #fff;
  cursor: pointer;
}

.agreement input:checked {
  border-color: var(--blue);
  background: var(--blue);
}

.agreement input:checked::after {
  position: absolute;
  top: 2px;
  left: 5px;
  width: 4px;
  height: 7px;
  border-right: 2px solid #fff;
  border-bottom: 2px solid #fff;
  content: '';
  transform: rotate(45deg);
}

.agreement input:focus-visible {
  outline: 3px solid rgb(11 104 189 / 20%);
  outline-offset: 2px;
}

.agreement label {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5;
}

.agreement a {
  color: var(--blue-deep);
  font-weight: 700;
  text-decoration: none;
}

.agreement-error {
  margin-top: -9px;
}

.register-button {
  display: flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 9px;
  margin-top: 2px;
  border: 0;
  border-radius: 6px;
  background: var(--blue);
  color: white;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 750;
}

.register-button:disabled {
  cursor: wait;
  opacity: 0.7;
}

.login-text {
  margin: 19px 0 0;
  color: #718087;
  font-size: 12px;
  text-align: center;
}

.login-text a {
  color: var(--blue-deep);
  font-weight: 750;
  text-decoration: none;
}

@keyframes enter {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 950px) {
  .nav-menu {
    gap: 14px;
    margin-left: 24px;
  }

  .nav-menu a {
    font-size: 12px;
  }

  .main-content {
    grid-template-columns: 1fr 1fr;
  }

  .left-section {
    padding-right: 28px;
  }

  .right-section {
    padding: 32px 24px;
  }

  .register-card {
    padding: 24px;
  }
}

@media (max-width: 720px) {
  .navbar {
    height: 64px;
  }

  .navbar-container {
    width: calc(100% - 32px);
  }

  .nav-menu {
    display: none;
  }

  .main-content {
    grid-template-columns: 1fr;
  }

  .left-section {
    justify-content: center;
    padding: 48px 24px 38px;
  }

  .left-content h1 {
    font-size: 42px;
  }

  .description {
    margin-bottom: 20px;
    font-size: 14px;
  }

  .feature-list {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .feature-list li {
    min-height: 42px;
  }

  .right-section {
    justify-content: center;
    padding: 16px 18px 42px;
  }

  .register-card {
    padding: 24px 20px;
  }
}

@media (max-width: 380px) {
  .btn-login,
  .btn-register {
    min-height: 36px;
    padding: 0 10px;
    font-size: 12px;
  }

  .brand-logo {
    gap: 6px;
    font-size: 14px;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
  }
}
</style>
