<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth.js'

defineProps({
  judul: { type: String, required: true },
  deskripsi: { type: String, default: '' },
  aksiTeks: { type: String, default: '' },
  aksiDisabled: { type: Boolean, default: false },
  memuat: { type: Boolean, default: false },
  galat: { type: String, default: '' },
})
const emit = defineEmits(['aksi', 'coba-lagi'])
const auth = useAuthStore()
const initials = computed(() =>
  (auth.nama || 'Super Admin').trim().split(/\s+/).map((word) => word[0]).slice(0, 2).join('').toUpperCase(),
)
</script>

<template>
  <header class="admin-heading mb-6 flex min-h-[76px] items-center justify-between gap-4 border-b border-[#e1e7f0] bg-[#f5f8fc] px-6 py-4">
    <div class="min-w-0">
      <h1 class="text-[18px] font-bold leading-tight text-[#16233a]">{{ judul }}</h1>
      <p v-if="deskripsi" class="mt-1 text-[12px] leading-relaxed text-[#65748c]">{{ deskripsi }}</p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <slot name="aksi-extra" />
      <Button
        v-if="aksiTeks"
        :label="aksiTeks"
        :disabled="aksiDisabled || memuat"
        @click="emit('aksi')"
      />
      <span class="ml-2 grid h-10 w-10 place-items-center rounded-full border-[3px] border-[#efb72b] bg-[#102a50] text-[12px] font-bold text-white" :title="auth.nama || 'Super Admin'">
        {{ initials }}
      </span>
    </div>
  </header>

  <p v-if="galat" class="mb-4 rounded-xl border border-[#f3c2c2] bg-[#fdf0f0] px-4 py-3 text-[12px] text-[#a33333]">
    {{ galat }}
    <button type="button" class="ml-2 font-bold underline" @click="emit('coba-lagi')">Coba lagi</button>
  </p>

  <slot />
</template>

<style scoped>
.admin-heading {
  margin-top: -28px;
  margin-right: -26px;
  margin-left: -26px;
}
@media (max-width: 780px) {
  .admin-heading { margin-top: -16px; margin-right: -12px; margin-left: -12px; padding: 14px 12px; }
}
</style>
