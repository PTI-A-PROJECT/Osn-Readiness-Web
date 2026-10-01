<script setup>
import { useRoute } from 'vue-router'
import UserMenu from '@/components/UserMenu.vue'

const route = useRoute()
</script>

<template>
  <div class="min-h-full bg-[#f5f8fc] text-[#0f1b33]">
    <header class="flex items-center justify-between border-b border-[#e6ebf2] bg-white px-7 py-4">
      <h1 class="text-[17px] font-bold leading-tight">{{ route.meta.title }}</h1>
      <UserMenu />
    </header>
    <main class="px-6 py-6">
      <section class="rounded-3xl border border-[#e6ebf2] bg-white p-10 text-center">
        <p class="text-base font-bold">Halaman {{ route.meta.title }} sedang disiapkan</p>
        <p class="mt-1.5 text-sm text-[#6b778c]">Ganti file ini dengan halaman aslinya.</p>
      </section>
    </main>
  </div>
</template>
