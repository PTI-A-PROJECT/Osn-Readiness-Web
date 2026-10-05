<script setup>
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { useAuthStore } from '@/stores/auth.js'
import { adminService } from '@/services/admin.js'
import { pesanError } from '@/lib/errors.js'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()

// Bukti role-guard backend bekerja dari browser:
// hanya super_admin yang bisa lolos RequireRole di GET /admin/ping.
async function tesAksesAdmin() {
  try {
    const pesan = await adminService.ping()
    toast.add({ severity: 'success', summary: 'Akses admin OK', detail: pesan, life: 4000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Akses admin ditolak', detail: pesanError(err), life: 4000 })
  }
}
async function keluar() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold">Halo, {{ auth.user?.nama }} (Admin)</h1>
    <Button label="Keluar" severity="secondary" @click="keluar" />
  </div>
  <Card>
    <template #content>
      <p class="mb-3 text-sm text-gray-600">Uji bahwa cookie dan role-guard backend bekerja dari browser.</p>
      <Button label="Tes akses admin" @click="tesAksesAdmin" />
    </template>
  </Card>
</template>
