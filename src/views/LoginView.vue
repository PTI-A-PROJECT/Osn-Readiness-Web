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
import AuthHeroPanel from '@/components/AuthHeroPanel.vue'

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
  email.value = email.value.trim()
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
  <div class="login fixed inset-0 z-50 overflow-y-auto flex flex-col font-['Inter',system-ui,sans-serif] text-[#0f1b3d] bg-white">
    <PublicNavbar current="login" />

    <main class="flex-1 grid grid-cols-1 min-[900px]:grid-cols-2">
      <AuthHeroPanel
        :title-lines="['Selamat Datang', 'Kembali!']"
        description="Persiapan OSN Informatika yang terarah: dari pemetaan kompetensi sampai simulasi sesuai standar TOKI."
        :items="highlights"
      />

      <!-- KANAN -->
      <section class="bg-[#eef2f7] flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-[400px] bg-white border border-[#e8edf3] rounded-[20px] px-8 pt-[34px] pb-[30px] shadow-[0_12px_30px_rgba(15,27,61,0.08)]">
          <h2 class="text-[22px] font-bold mb-1.5">Masuk ke Akun</h2>
          <p class="text-[13px] leading-[1.5] text-[#64748b] mb-6">
            Lanjutkan latihan soal, simulasi, dan pelajari materi kompetisi Informatika.
          </p>

          <form class="flex flex-col gap-4" @submit.prevent="masuk">
            <div class="flex flex-col gap-1.5">
              <label for="email" class="text-[13px] font-semibold text-[#0f1b3d]">Alamat Email</label>
              <div class="relative">
                <span class="absolute left-[0.85rem] top-1/2 -translate-y-1/2 flex text-[#94a3b8] pointer-events-none z-[1]">
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
              <small v-if="emailError" class="text-[11px] text-[#ef4444]">{{ emailError }}</small>
            </div>

            <div class="flex flex-col gap-1.5">
              <div class="flex items-center justify-between">
                <label for="password" class="text-[13px] font-semibold text-[#0f1b3d]">Kata Sandi</label>
                <a href="#" class="text-xs font-semibold text-[#1e4b8f] hover:underline cursor-pointer" @click.prevent="lupaPassword">Lupa kata sandi?</a>
              </div>
              <div class="relative">
                <span class="absolute left-[0.85rem] top-1/2 -translate-y-1/2 flex text-[#94a3b8] pointer-events-none z-[1]">
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
              <small v-if="passwordError" class="text-[11px] text-[#ef4444]">{{ passwordError }}</small>
            </div>

            <label class="flex items-center gap-2 text-[13px] text-[#334155] cursor-pointer">
              <input v-model="remember" type="checkbox" class="w-3.5 h-3.5 accent-[#1e4b8f] cursor-pointer" />
              <span>Ingat saya di perangkat ini</span>
            </label>

            <Button type="submit" label="Masuk →" :loading="loading" class="login-btn" />
          </form>

          <div class="flex items-center gap-2.5 my-5 text-[11.5px] text-[#94a3b8]">
            <span class="flex-1 h-px bg-[#e2e8f0]"></span>
            <span>atau masuk dengan</span>
            <span class="flex-1 h-px bg-[#e2e8f0]"></span>
          </div>

          <Button type="button" class="google-btn" @click="masukGoogle">
            <svg width="16" height="16" viewBox="0 0 48 48">
              <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z" />
              <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z" />
              <path fill="#FBBC05" d="M10.5 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.8l7.9-6.1z" />
              <path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z" />
            </svg>
            <span>Masuk dengan Google</span>
          </Button>

          <p class="text-center text-[13px] text-[#64748b] mt-[18px]">
            Belum memiliki akun?
            <RouterLink to="/register" class="text-[#0f1b3d] font-bold hover:text-[#1e4b8f] hover:underline">Daftar akun siswa</RouterLink>
          </p>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* Override PrimeVue (tak bisa via utility); layout sudah Tailwind.
   Input password PrimeVue memakai kelas .p-password-input, bukan
   .p-inputtext — keduanya harus dioverride agar ikon kiri tak
   menimpa huruf pertama. */
:deep(.p-inputtext),
:deep(.p-password-input) {
  width: 100%;
  height: 46px;
  padding-left: 2.4rem;
  border-radius: 10px;
  font-size: 13.5px;
  background: #fff;
  color: #0f1b3d;
  border-color: #e2e8f0;
}
/* Ruang untuk ikon mata bawaan PrimeVue di kanan input sandi. */
:deep(.p-password-input) {
  padding-right: 2.4rem;
}
:deep(.p-inputtext::placeholder),
:deep(.p-password-input::placeholder) {
  color: #a0aec0;
}
:deep(.p-inputtext:enabled:focus),
:deep(.p-password-input:enabled:focus) {
  background: #fff;
  border-color: #1e4b8f;
  box-shadow: 0 0 0 3px rgba(30, 75, 143, 0.12);
}
.login-btn.p-button {
  width: 100%;
  justify-content: center;
  padding: 15px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  background: #1e4b8f;
  border: 1px solid #1e4b8f;
  color: #fff;
  white-space: nowrap;
}
.login-btn.p-button:not(:disabled):hover {
  background: #183d75;
  border-color: #183d75;
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
  border: 1px solid #e2e8f0;
  color: #0f1b3d;
  white-space: nowrap;
}
.google-btn.p-button:not(:disabled):hover {
  background: #e8edf3;
}
</style>
