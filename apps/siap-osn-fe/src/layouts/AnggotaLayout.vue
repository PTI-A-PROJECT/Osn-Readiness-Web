<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()
const router = useRouter()

async function keluar() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="anggota-shell flex min-h-screen bg-[#f5f8fc]">
    <aside class="anggota-sidebar sticky top-0 flex h-screen w-[200px] shrink-0 flex-col bg-[#0a1f3f] px-3 py-5 text-white">
      <div class="mb-6 flex items-center gap-2.5 px-1.5">
        <img src="/logo-icon.png" alt="Logo SIAP OSN" class="h-7 w-7 shrink-0 rounded-full object-contain" />
        <span class="text-[13px] font-bold tracking-tight">SIAP OSN</span>
      </div>
      <nav aria-label="Menu anggota" class="anggota-nav flex flex-col gap-1">
        <RouterLink :to="{ name: 'anggota.dashboard' }" exact-active-class="is-active" class="anggota-nav-link nav-link flex min-h-[44px] items-center rounded-xl px-2.5 py-2.5 text-[11px] leading-tight text-[#91a0b6] transition-colors hover:bg-white/5 hover:text-white">
          Dashboard
        </RouterLink>
        <RouterLink :to="{ name: 'anggota.kode' }" exact-active-class="is-active" class="anggota-nav-link nav-link flex min-h-[44px] items-center rounded-xl px-2.5 py-2.5 text-[11px] leading-tight text-[#91a0b6] transition-colors hover:bg-white/5 hover:text-white">
          Kode Referal
        </RouterLink>
      </nav>
      <div class="anggota-footer mt-auto border-t border-white/10 px-1.5 pt-4">
        <p class="truncate text-sm font-semibold">{{ auth.nama }}</p>
        <button type="button" class="mt-3 min-h-[44px] w-full cursor-pointer rounded-lg border border-white/20 px-3 py-2 text-left text-xs font-semibold text-[#d7e1ef] hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" @click="keluar">
          Keluar
        </button>
      </div>
    </aside>
    <div class="anggota-main min-w-0 flex-1 px-4 py-5 sm:px-6 sm:py-7">
      <RouterView />
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 600px) {
  .anggota-shell {
    flex-direction: column;
  }
  .anggota-sidebar {
    position: static;
    width: 100%;
    height: auto;
    padding: 12px;
  }
  .anggota-sidebar > div:first-child {
    margin-bottom: 8px;
  }
  .anggota-nav {
    flex-direction: row;
  }
  .anggota-nav-link {
    flex: 1;
    justify-content: center;
  }
  .anggota-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 8px;
    padding: 8px 0 0;
    border-top: 1px solid rgb(255 255 255 / 10%);
  }
  .anggota-footer button {
    width: auto;
    margin-top: 0;
  }
}

.nav-link.is-active {
  background: rgb(240 179 35 / 0.16);
  color: #f0b323;
  font-weight: 600;
}
</style>
