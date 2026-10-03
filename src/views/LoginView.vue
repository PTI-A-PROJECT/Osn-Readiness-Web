<script setup>
import PublicNavbar from '@/components/PublicNavbar.vue'
import AuthHeroPanel from '@/components/AuthHeroPanel.vue'
import LoginForm from '@/components/auth/LoginForm.vue'

const highlights = [
  'Pre-test & pemetaan kompetensi',
  'Materi sesuai kebutuhanmu',
  'Simulasi seleksi standar TOKI',
]
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
      <LoginForm />
    </main>
  </div>
</template>
