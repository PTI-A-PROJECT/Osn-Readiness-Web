<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { useProgressStore } from '@/stores/progress.js'

const auth = useAuthStore()
const progress = useProgressStore()

// Ikon: path SVG (viewBox 24x24, stroke). Tidak perlu library tambahan.
const ICON = {
  dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  pemetaan: 'M3 3v18h18M7 16v-5M12 16V8M17 16v-3',
  materi: 'M2 4h6a4 4 0 014 4v13a3 3 0 00-3-3H2zM22 4h-6a4 4 0 00-4 4v13a3 3 0 013-3h7z',
  progress: 'M3 17l6-6 4 4 8-8M15 7h6v6',
  simulasi: 'M4 4h16a1 1 0 011 1v11a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1zM8 21h8M12 17v4M7 9l3 2-3 2M12 13h4',
  riwayat: 'M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8M3 3v5h5M12 7v5l4 2',
}

// Nama route harus sama dengan yang ada di router/index.js
const menuSiswa = [
  { label: 'Dashboard', to: { name: 'siswa.dashboard' }, icon: ICON.dashboard },
  { label: 'Pemetaan Kompetensi', to: { name: 'siswa.pemetaan' }, icon: ICON.pemetaan },
  { label: 'Materi', to: { name: 'siswa.materi' }, icon: ICON.materi },
  { label: 'Progress Belajar', to: { name: 'siswa.progress' }, icon: ICON.progress },
  { label: 'Simulasi Seleksi', to: { name: 'siswa.simulasi' }, icon: ICON.simulasi },
  { label: 'Riwayat Hasil', to: { name: 'siswa.riwayat' }, icon: ICON.riwayat },
]
const menuAdmin = [{ label: 'Dashboard', to: { name: 'admin.dashboard' }, icon: ICON.dashboard }]

const isAdmin = computed(() => auth.user?.role === 'super_admin')
const menu = computed(() => (isAdmin.value ? menuAdmin : menuSiswa))

// Streak dihitung dari aktivitas nyata siswa; 0 untuk akun yang belum mengerjakan apa pun.
const streak = computed(() => progress.data.streak)

// Sidebar ada di semua halaman siswa, jadi data progress dimuat dari sini.
onMounted(() => {
  if (!isAdmin.value) progress.fetchDashboard()
})
</script>

<template>
  <div class="flex min-h-screen bg-[#f5f8fc]">
    <!-- Sidebar -->
    <aside class="sticky top-0 flex h-screen w-[250px] shrink-0 flex-col bg-[#0a1f47] px-4 py-6">
      <div class="mb-6 flex items-center gap-3 px-2">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#0a1f47]">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="ICON.materi" />
          </svg>
        </span>
        <span class="text-[15px] font-bold tracking-tight text-white">SIAP OSN</span>
      </div>

      <nav class="flex flex-col gap-1.5" aria-label="Menu utama">
        <RouterLink
          v-for="item in menu"
          :key="item.label"
          :to="item.to"
          exact-active-class="is-active"
          class="nav-link flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm text-white/60 transition-colors hover:bg-white/5 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#f0b323]"
        >
          <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="item.icon" />
          </svg>
          {{ item.label }}
        </RouterLink>
      </nav>

      <div v-if="!isAdmin" class="mt-6 rounded-2xl border border-[#2a5db0]/60 bg-[#12336f] p-4 text-white">
        <template v-if="streak > 0">
          <p class="text-base font-bold leading-snug">{{ streak }} hari berturut-turut!</p>
          <p class="mt-1 text-xs leading-relaxed text-white/70">
            Belajar lagi hari ini biar streak-mu terus jalan
          </p>
        </template>
        <template v-else>
          <p class="text-base font-bold leading-snug">Belum ada streak</p>
          <p class="mt-1 text-xs leading-relaxed text-white/70">
            Mulai belajar hari ini untuk memulai streak-mu
          </p>
        </template>
      </div>
    </aside>

    <!-- Konten halaman -->
    <main class="min-w-0 flex-1">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.nav-link.is-active {
  background: rgb(240 179 35 / 0.14);
  color: #f0b323;
  font-weight: 600;
}
</style>
