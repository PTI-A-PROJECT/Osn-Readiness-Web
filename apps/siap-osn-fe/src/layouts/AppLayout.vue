<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import { useProgressStore } from '@/stores/progress.js'

const route = useRoute()
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
  siswa: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
  aturan: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z',
}

// Menu siswa mengikuti desain dashboard terbaru.
const menuSiswa = [
  { label: 'Dashboard', to: { name: 'siswa.dashboard' }, icon: ICON.dashboard },
  { label: 'Pemetaan Kompetensi', to: { name: 'siswa.pemetaan' }, icon: ICON.pemetaan },
  { label: 'Materi', to: { name: 'siswa.materi' }, icon: ICON.materi },
  { label: 'Simulasi Seleksi', to: { name: 'siswa.simulasi' }, icon: ICON.simulasi },
  { label: 'Riwayat Hasil', to: { name: 'siswa.riwayat' }, icon: ICON.riwayat },
]

// Menu admin dipertahankan seperti sebelumnya.
const menuAdmin = [
  { label: 'Dashboard', to: { name: 'admin.dashboard' }, icon: ICON.dashboard },
  { label: 'Kelola Siswa', to: { name: 'admin.siswa' }, icon: ICON.siswa },
  { label: 'Kompetensi & Materi', to: { name: 'admin.konten' }, icon: ICON.materi },
  { label: 'Soal & Pembahasan', to: { name: 'admin.soal' }, icon: ICON.dashboard },
  { label: 'Latihan & Simulasi', to: { name: 'admin.ujian' }, icon: ICON.simulasi },
  { label: 'Tingkat & Aturan', to: { name: 'admin.aturan' }, icon: ICON.aturan },
]

const isAdmin = computed(() => auth.user?.role === 'super_admin')
const menu = computed(() => (isAdmin.value ? menuAdmin : menuSiswa))

// Sidebar ada di semua halaman siswa, jadi data progress dimuat dari sini.
onMounted(() => {
  if (!isAdmin.value) progress.fetchDashboard()
})
</script>

<template>
  <div class="flex min-h-screen bg-[#f5f8fc]">
    <!-- Sidebar -->
    <aside
      v-if="route.name !== 'siswa.pretest'"
      class="sticky top-0 flex h-screen shrink-0 flex-col bg-[#0a1f3f] text-white"
      :class="isAdmin ? 'w-[250px] px-4 py-6' : 'w-[182px] px-3 py-5'"
    >
      <div
        class="mb-6 flex items-center"
        :class="isAdmin ? 'gap-3 px-2' : 'gap-2.5 px-1.5'"
      >
        <img
          src="/logo-icon.png"
          alt="Logo SIAP OSN"
          class="shrink-0 rounded-full object-contain"
          :class="isAdmin ? 'h-10 w-10' : 'h-7 w-7'"
        />

        <span
          class="font-bold tracking-tight text-white"
          :class="isAdmin ? 'text-[15px]' : 'text-[13px]'"
        >
          SIAP OSN
        </span>
      </div>

      <nav
        class="flex flex-col"
        :class="isAdmin ? 'gap-1.5' : 'gap-1'"
        aria-label="Menu utama"
      >
        <RouterLink
          v-for="item in menu"
          :key="item.label"
          :to="item.to"
          exact-active-class="is-active"
          class="nav-link flex items-center rounded-xl transition-colors hover:bg-white/5 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#f0b323]"
          :class="isAdmin
            ? 'gap-3 px-3.5 py-3 text-sm text-white/60'
            : 'gap-2 px-2.5 py-2.5 text-[11px] leading-tight text-[#91a0b6]'"
        >
          <svg
            class="shrink-0"
            :class="isAdmin ? 'h-[18px] w-[18px]' : 'h-[14px] w-[14px]'"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path :d="item.icon" />
          </svg>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <!-- Kartu motivasi siswa. Tidak menampilkan angka streak palsu
           karena backend belum menyediakan data streak. -->
      <div
        v-if="!isAdmin"
        class="mt-4 rounded-xl bg-[#17477e] px-3 py-3"
      >
        <p class="text-[13px] font-bold leading-tight text-white">
          Terus jaga semangat!
        </p>
        <p class="mt-1.5 text-[10px] leading-snug text-[#b8c9df]">
          Belajar sedikit setiap hari untuk menjaga progres persiapan OSN-mu.
        </p>
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
  background: rgb(240 179 35 / 0.16);
  color: #f0b323;
  font-weight: 600;
}
</style>
