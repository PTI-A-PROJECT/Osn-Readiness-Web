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

// Menu admin dipisah menurut desain, tetapi tetap menggunakan halaman CRUD
// yang sudah ada. Query tab memilih modul yang sesuai tanpa duplikasi form.
const menuAdmin = [
  { kelompok: 'UTAMA', label: 'Dashboard', to: { name: 'admin.dashboard' } },
  { kelompok: 'UTAMA', label: 'Manajemen Siswa', to: { name: 'admin.siswa' } },
  { kelompok: 'UTAMA', label: 'Pantau Anggota', to: { name: 'admin.anggota' } },
  { kelompok: 'UTAMA', label: 'Manajemen Tingkat Seleksi', to: { name: 'admin.aturan', query: { tab: 'tingkat' } } },
  { kelompok: 'UTAMA', label: 'Manajemen Kompetensi', to: { name: 'admin.konten', query: { tab: 'kompetensi' } } },
  { kelompok: 'UTAMA', label: 'Manajemen Materi', to: { name: 'admin.konten', query: { tab: 'materi' } } },
  { kelompok: 'ASESMEN', label: 'Manajemen Soal', to: { name: 'admin.soal', query: { tab: 'soal' } } },
  { kelompok: 'ASESMEN', label: 'Manajemen Simulasi', to: { name: 'admin.ujian', query: { tab: 'simulasi' } } },
  { kelompok: 'ASESMEN', label: 'Manajemen Pembahasan', to: { name: 'admin.soal', query: { tab: 'pembahasan' } } },
  { kelompok: 'ASESMEN', label: 'Manajemen Aturan Pemetaan', to: { name: 'admin.aturan', query: { tab: 'aturan' } } },
  { kelompok: 'AUDIT', label: 'Log Aktivitas', to: { name: 'admin.aktivitas' } },
]

const isAdmin = computed(() => auth.user?.role === 'super_admin')
const kelompokAdmin = ['UTAMA', 'ASESMEN', 'AUDIT']
function adminMenuAktif(item) {
  return route.name === item.to.name &&
    (!item.to.query?.tab || (route.query.tab || ({ 'admin.konten': 'kompetensi', 'admin.soal': 'soal', 'admin.ujian': 'latihan', 'admin.aturan': 'tingkat' }[route.name])) === item.to.query.tab)
}

// Sidebar ada di semua halaman siswa, jadi data progress dimuat dari sini.
onMounted(() => {
  if (!isAdmin.value) progress.fetchDashboard()
})
</script>

<template>
  <div class="flex min-h-screen bg-[#f5f8fc]">
    <!-- Sidebar khusus Super Admin -->
    <aside v-if="isAdmin" class="admin-sidebar sticky top-0 flex h-screen w-[230px] shrink-0 flex-col overflow-y-auto bg-[#0b203d] px-3.5 py-5 text-white">
      <div class="mb-6 flex items-center gap-2 px-1">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px] bg-[#f2b628] text-[15px] font-extrabold text-[#122541]">S</span>
        <strong class="whitespace-nowrap text-[13px] tracking-tight">SIAP OSN</strong>
        <span class="ml-auto whitespace-nowrap rounded-full bg-[#26364a] px-1.5 py-1 text-[9px] font-bold text-[#f2b628]">Super Admin</span>
      </div>

      <nav class="flex flex-1 flex-col gap-4" aria-label="Navigasi admin">
        <section v-for="kelompok in kelompokAdmin" :key="kelompok">
          <h2 class="mb-2 px-2 text-[10px] font-bold uppercase tracking-wider text-[#7187a8]">{{ kelompok }}</h2>
          <div class="space-y-1">
            <RouterLink
              v-for="item in menuAdmin.filter((nav) => nav.kelompok === kelompok)"
              :key="item.label"
              :to="item.to"
              class="admin-nav-item block rounded-lg px-3 py-2.5 text-[12px] leading-[1.35] text-[#a8b8cf] transition-colors hover:bg-white/10 hover:text-white"
              :class="{ 'admin-active': adminMenuAktif(item) }"
              :aria-current="adminMenuAktif(item) ? 'page' : undefined"
            >
              {{ item.label }}
            </RouterLink>
          </div>
        </section>
      </nav>

      <div class="mt-5 rounded-xl bg-[#192e4c] px-3 py-3">
        <p class="text-[12px] font-bold text-white">Panel Super Admin</p>
        <p class="mt-1 text-[10px] leading-snug text-[#93aac8]">Pengelolaan siswa, konten, dan asesmen SIAP OSN.</p>
      </div>
    </aside>

    <!-- Sidebar siswa tidak berubah -->
    <aside
      v-else-if="route.name !== 'siswa.pretest'"
      class="sticky top-0 flex h-screen w-[182px] shrink-0 flex-col bg-[#0a1f3f] px-3 py-5 text-white"
    >
      <div class="mb-6 flex items-center gap-2.5 px-1.5">
        <img src="/logo-icon.png" alt="Logo SIAP OSN" class="h-7 w-7 shrink-0 rounded-full object-contain" />
        <span class="text-[13px] font-bold tracking-tight text-white">SIAP OSN</span>
      </div>
      <nav class="flex flex-col gap-1" aria-label="Menu siswa">
        <RouterLink
          v-for="item in menuSiswa"
          :key="item.label"
          :to="item.to"
          exact-active-class="is-active"
          class="nav-link flex items-center gap-2 rounded-xl px-2.5 py-2.5 text-[11px] leading-tight text-[#91a0b6] transition-colors hover:bg-white/5 hover:text-white"
        >
          <svg class="h-[14px] w-[14px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="item.icon" />
          </svg>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
      <div class="mt-4 rounded-xl bg-[#17477e] px-3 py-3">
        <p class="text-[13px] font-bold leading-tight text-white">Terus jaga semangat!</p>
        <p class="mt-1.5 text-[10px] leading-snug text-[#b8c9df]">Belajar sedikit setiap hari untuk menjaga progres persiapan OSN-mu.</p>
      </div>
    </aside>

    <!-- Konten halaman -->
    <main class="min-w-0 flex-1" :class="isAdmin ? 'admin-main' : ''">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin-nav-item.admin-active {
  background: #303b45;
  color: #ffc01d;
  font-weight: 750;
}
.admin-main {
  min-width: 0;
  padding: 28px 26px 44px;
  background: #f4f7fc;
  color: #17253f;
  font-family: Inter, sans-serif;
}
.admin-main :deep(h1),
.admin-main :deep(h2),
.admin-main :deep(h3),
.admin-main :deep(strong) { color: #14233f; }
.admin-main :deep(input:not([type='checkbox']):not([type='radio']):not([type='file'])),
.admin-main :deep(select),
.admin-main :deep(textarea),
.admin-main :deep(.p-inputtext),
.admin-main :deep(.p-inputnumber-input),
.admin-main :deep(.p-select) {
  color: #182945;
  background: #fff;
  border: 1px solid #d9e3f1;
  border-radius: 9px;
  min-height: 38px;
  opacity: 1;
}
.admin-main :deep(input::placeholder),
.admin-main :deep(textarea::placeholder) { color: #75859b; opacity: 1; }
.admin-main :deep(input:focus),
.admin-main :deep(select:focus),
.admin-main :deep(textarea:focus) {
  outline: 2px solid #a4bde8;
  outline-offset: 1px;
}
.admin-main :deep(.p-button.p-button-outlined) {
  color: #234269;
  border-color: #cdd8e8;
  background: #fff;
}
.admin-main :deep(.p-button.p-button-outlined:disabled) { opacity: .6; }
.admin-main :deep(.p-button) { min-height: 34px; font-weight: 600; }
.admin-main :deep(.p-card h2),
.admin-main :deep(.p-card h3) { color: #14233f; }
.admin-main :deep(.p-card) { color: #17253f; }
.admin-main :deep(.p-card table) { font-size: 13px; }
.admin-main :deep(.p-card td) { color: #273b59; }
.admin-main :deep(.p-card th) { font-weight: 700; }
.admin-main :deep(.p-card td),
.admin-main :deep(.p-card th) { padding-top: 12px; padding-bottom: 12px; }
.admin-main :deep(.p-card .p-card-body) { overflow-x: auto; }

.admin-main :deep(.p-card) {
  border: 1px solid #e0e7f2;
  border-radius: 18px;
  background: #fff;
  box-shadow: none;
}
.admin-main :deep(.p-card-body) { padding: 21px; }
.admin-main :deep(.p-card-content) { padding: 0; }
.admin-main :deep(.p-button:not(.p-button-outlined):not(.p-button-text)) {
  border-color: #f0b52a;
  background: #f0b52a;
  color: #14213b;
}
.admin-main :deep(table) { width: 100%; border-collapse: collapse; }
.admin-main :deep(th) { color: #65758f; }
.admin-main :deep(td), .admin-main :deep(th) { border-bottom: 1px solid #e6edf6; }
@media (max-width: 780px) {
  .admin-sidebar { width: 185px; }
  .admin-main { padding: 16px 12px; }
}
.nav-link.is-active {
  background: rgb(240 179 35 / 0.16);
  color: #f0b323;
  font-weight: 600;
}
</style>
