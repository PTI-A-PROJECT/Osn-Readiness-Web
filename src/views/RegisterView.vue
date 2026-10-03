<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth.js'
import { pesanError } from '@/lib/errors.js'
import PublicNavbar from '@/components/PublicNavbar.vue'

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
  nama.value = nama.value.trim()
  email.value = email.value.trim()
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
    await auth.register({ nama: nama.value, email: email.value, password: password.value, konfirmasi: konfirmasi.value })
    toast.add({ severity: 'success', summary: 'Registrasi berhasil', detail: 'Silakan masuk dengan akun baru', life: 4000 })
    router.push('/login')
  } catch (err) {
    // Laravel: 422 + { message, errors: { email: [...] } } (pesan Inggris).
    // Backend Go lama: 400 + "Email sudah terdaftar". Tangani keduanya.
    const emailGanda =
      err?.response?.data?.errors?.email?.[0] ??
      (err?.response?.data?.message === 'Email sudah terdaftar' ? 'Email sudah terdaftar' : null)
    if (emailGanda && (err?.response?.status === 400 || err?.response?.status === 422)) {
      emailError.value = emailGanda.includes('already been taken') ? 'Email sudah terdaftar' : emailGanda
    } else {
      toast.add({ severity: 'error', summary: 'Registrasi gagal', detail: pesanError(err), life: 4000 })
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen text-[#17233b] bg-white font-['Avenir_Next',Avenir,'Segoe_UI',sans-serif]">
    <PublicNavbar current="register" />

    <main class="grid grid-cols-1 min-[720px]:grid-cols-[1.05fr_0.95fr] min-h-[calc(100vh-72px)]">
      <section id="keunggulan" class="flex items-center justify-center px-6 pt-12 pb-[38px] min-[720px]:justify-end min-[720px]:py-16 min-[720px]:pl-6 min-[720px]:pr-7 min-[950px]:pr-[clamp(32px,6vw,88px)] bg-[radial-gradient(ellipse_at_15%_90%,#e6f1fb_0,transparent_42%),#fff]">
        <div class="w-full max-w-[480px] anim-a">
          <p class="text-[#0759a5] text-[11px] font-extrabold mb-[14px] tracking-[1px]">SIAPKAN DIRIMU UNTUK OSN</p>
          <h1 class="mb-5 text-[#10294a] text-[42px] min-[720px]:text-[52px] font-extrabold leading-[1.03]">Selamat<br />Datang!</h1>
          <p class="max-w-[460px] mb-5 min-[720px]:mb-[30px] text-[#154b86] text-sm min-[720px]:text-base leading-[1.65]">
            Persiapan OSN Informatika yang terarah: dari pemetaan kompetensi sampai simulasi sesuai standar TOKI.
          </p>
          <ul class="grid gap-2 min-[720px]:gap-[11px] m-0 p-0 list-none">
            <li class="flex min-h-[42px] min-[720px]:min-h-12 items-center gap-3 px-3.5 border border-[#dce7f1] rounded-[7px] bg-[rgb(255_255_255/78%)] text-[#182f50] text-[13px] font-bold"><span class="grid w-[22px] aspect-square flex-none place-items-center rounded-full bg-[#e3eef8] text-[#0759a5] text-[13px]" aria-hidden="true">✓</span>Pre-test &amp; pemetaan kompetensi</li>
            <li class="flex min-h-[42px] min-[720px]:min-h-12 items-center gap-3 px-3.5 border border-[#dce7f1] rounded-[7px] bg-[rgb(255_255_255/78%)] text-[#182f50] text-[13px] font-bold"><span class="grid w-[22px] aspect-square flex-none place-items-center rounded-full bg-[#e3eef8] text-[#0759a5] text-[13px]" aria-hidden="true">✓</span>Materi sesuai kebutuhanmu</li>
            <li class="flex min-h-[42px] min-[720px]:min-h-12 items-center gap-3 px-3.5 border border-[#dce7f1] rounded-[7px] bg-[rgb(255_255_255/78%)] text-[#182f50] text-[13px] font-bold"><span class="grid w-[22px] aspect-square flex-none place-items-center rounded-full bg-[#e3eef8] text-[#0759a5] text-[13px]" aria-hidden="true">✓</span>Simulasi seleksi standar TOKI</li>
          </ul>
        </div>
      </section>

      <section id="register-form" class="flex items-center justify-center px-[18px] pt-4 pb-[42px] min-[720px]:justify-start min-[720px]:px-6 min-[720px]:py-8 min-[950px]:px-[clamp(28px,5.4vw,78px)] min-[950px]:py-[42px] bg-[#f0f5fb]">
        <div class="w-full max-w-[440px] mx-auto p-6 px-5 min-[720px]:px-6 min-[950px]:p-[30px] border border-[#dce4ed] rounded-[9px] bg-white shadow-[0_12px_35px_rgb(21_48_80/7%)] anim-b">
          <div class="mb-[23px]">
            <p class="text-[#0759a5] text-[11px] font-extrabold mb-2 tracking-[0.7px]">MULAI PERJALANANMU</p>
            <h2 class="text-[#172033] text-[22px] font-extrabold">Daftar Akun Baru</h2>
            <p class="mt-[7px] text-[#68758a] text-[13px]">Buat akun untuk mulai mempersiapkan diri.</p>
          </div>

          <form class="grid gap-[13px]" @submit.prevent="daftar">
            <div class="grid gap-1.5">
              <label for="nama" class="text-[#182238] text-xs font-[750]">Nama Lengkap <span class="text-[#c54c45]">*</span></label>
              <input id="nama" v-model="nama" autocomplete="name" type="text" placeholder="Masukkan nama lengkap" :aria-invalid="Boolean(namaError)" :aria-describedby="namaError ? 'nama-error' : undefined" class="w-full min-h-[42px] px-3 border border-[#d6e0eb] rounded-md outline-none bg-white text-[#17233b] font-inherit text-[13px] placeholder:text-[#95a5aa] focus:border-[#0759a5] focus:shadow-[0_0_0_3px_rgb(11_104_189/11%)] aria-invalid:border-[#c54c45]" />
              <small v-if="namaError" id="nama-error" class="text-[11px] text-[#b33a35]">{{ namaError }}</small>
            </div>

            <div class="grid gap-1.5">
              <label for="email" class="text-[#182238] text-xs font-[750]">Email Aktif <span class="text-[#c54c45]">*</span></label>
              <input id="email" v-model="email" autocomplete="email" type="email" placeholder="nama@sekolah.sch.id" :aria-invalid="Boolean(emailError)" :aria-describedby="emailError ? 'email-error' : undefined" class="w-full min-h-[42px] px-3 border border-[#d6e0eb] rounded-md outline-none bg-white text-[#17233b] font-inherit text-[13px] placeholder:text-[#95a5aa] focus:border-[#0759a5] focus:shadow-[0_0_0_3px_rgb(11_104_189/11%)] aria-invalid:border-[#c54c45]" />
              <small v-if="emailError" id="email-error" class="text-[11px] text-[#b33a35]">{{ emailError }}</small>
            </div>

            <div class="grid gap-1.5">
              <label for="password" class="text-[#182238] text-xs font-[750]">Kata Sandi <span class="text-[#c54c45]">*</span></label>
              <input id="password" v-model="password" autocomplete="new-password" type="password" placeholder="Buat kata sandi" :aria-invalid="Boolean(passwordError)" :aria-describedby="passwordError ? 'password-error' : 'password-hint'" class="w-full min-h-[42px] px-3 border border-[#d6e0eb] rounded-md outline-none bg-white text-[#17233b] font-inherit text-[13px] placeholder:text-[#95a5aa] focus:border-[#0759a5] focus:shadow-[0_0_0_3px_rgb(11_104_189/11%)] aria-invalid:border-[#c54c45]" />
              <small v-if="passwordError" id="password-error" class="text-[11px] text-[#b33a35]">{{ passwordError }}</small>
              <small v-else id="password-hint" class="text-[11px] text-[#68758a]">Minimal 8 karakter, kombinasi huruf dan angka</small>
            </div>

            <div class="grid gap-1.5">
              <label for="konfirmasi" class="text-[#182238] text-xs font-[750]">Konfirmasi Kata Sandi <span class="text-[#c54c45]">*</span></label>
              <input id="konfirmasi" v-model="konfirmasi" autocomplete="new-password" type="password" placeholder="Ulangi kata sandi Anda" :aria-invalid="Boolean(konfirmasiError)" :aria-describedby="konfirmasiError ? 'konfirmasi-error' : undefined" class="w-full min-h-[42px] px-3 border border-[#d6e0eb] rounded-md outline-none bg-white text-[#17233b] font-inherit text-[13px] placeholder:text-[#95a5aa] focus:border-[#0759a5] focus:shadow-[0_0_0_3px_rgb(11_104_189/11%)] aria-invalid:border-[#c54c45]" />
              <small v-if="konfirmasiError" id="konfirmasi-error" class="text-[11px] text-[#b33a35]">{{ konfirmasiError }}</small>
            </div>

            <div class="agreement flex items-start gap-[9px] mt-[3px]">
              <input id="agreement" v-model="setuju" type="checkbox" :aria-invalid="Boolean(setujuError)" :aria-describedby="setujuError ? 'agreement-error' : undefined" />
              <label for="agreement" class="text-[#68758a] text-[11px] leading-[1.5]">Saya menyetujui <a href="#agreement" class="text-[#064d91] font-bold no-underline">Ketentuan Layanan</a> dan <a href="#agreement" class="text-[#064d91] font-bold no-underline">Kebijakan Privasi</a> SIAP OSN</label>
            </div>
            <small v-if="setujuError" id="agreement-error" class="text-[11px] text-[#b33a35] -mt-[9px]">{{ setujuError }}</small>

            <button class="flex min-h-11 items-center justify-center gap-[9px] mt-0.5 rounded-md bg-[#0759a5] text-white text-[13px] font-[750] cursor-pointer hover:bg-[#064d91] disabled:cursor-wait disabled:opacity-70" type="submit" :disabled="loading">
              <span>{{ loading ? 'Memproses...' : 'Daftar Sekarang' }}</span>
              <span aria-hidden="true">→</span>
            </button>
          </form>

          <p class="mt-[19px] text-[#718087] text-xs text-center">Sudah punya akun? <RouterLink to="/login" class="text-[#064d91] font-[750] no-underline hover:text-[#0759a5]">Masuk di sini</RouterLink></p>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* Sisa non-Tailwind: keyframes + checkbox kustom (pseudo-element). */
@keyframes enter {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.anim-a {
  animation: enter 500ms ease-out both;
}
.anim-b {
  animation: enter 600ms 80ms ease-out both;
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
  border-color: #0759a5;
  background: #0759a5;
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
