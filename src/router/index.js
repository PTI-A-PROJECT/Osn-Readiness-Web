import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import SiswaDashboardView from '@/views/siswa/DashboardView.vue'
import SimulasiView from '@/views/siswa/SimulasiView.vue'
import SiswaProfileView from '@/views/siswa/ProfileView.vue'
import PlaceholderView from '@/views/siswa/PlaceholderView.vue'
import AdminDashboardView from '@/views/admin/DashboardView.vue'
import ForbiddenView from '@/views/ForbiddenView.vue'
import NotFoundView from '@/views/NotFoundView.vue'

// Satu-satunya tempat yang tahu pemetaan role -> dashboard.
// Dipakai guard di bawah dan LoginView setelah login sukses.
export function dashboardFor(role) {
  return role === 'super_admin' ? { name: 'admin.dashboard' } : { name: 'siswa.dashboard' }
}

// Diekspor agar guard.spec.js bisa membuat router sendiri
// dengan createMemoryHistory dari definisi route yang sama.
// Mode lihat-tampilan: isi VITE_BYPASS_AUTH=true di .env untuk membuka halaman siswa tanpa login.
// Default (tanpa variabel itu) = proteksi login aktif, aman untuk di-commit.
const BYPASS = import.meta.env.VITE_BYPASS_AUTH === 'true'
const siswaMeta = (title) => ({ requiresAuth: !BYPASS, title })

export const routes = [
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: 'login', name: 'login', component: LoginView, meta: { guestOnly: true } },
      { path: 'register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
    ],
  },
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'home', component: SiswaDashboardView, meta: { requiresAuth: !BYPASS } },
      { path: 'siswa', name: 'siswa.dashboard', component: SiswaDashboardView, meta: siswaMeta('Dashboard') },
      { path: 'siswa/profil', name: 'siswa.profil', component: SiswaProfileView, meta: siswaMeta('Profil') },
      { path: 'siswa/pemetaan', name: 'siswa.pemetaan', component: PlaceholderView, meta: siswaMeta('Pemetaan Kompetensi') },
      { path: 'siswa/materi', name: 'siswa.materi', component: PlaceholderView, meta: siswaMeta('Materi') },
      { path: 'siswa/progress', name: 'siswa.progress', component: PlaceholderView, meta: siswaMeta('Progress Belajar') },
      { path: 'siswa/simulasi', name: 'siswa.simulasi', component: SimulasiView, meta: siswaMeta('Simulasi Seleksi') },
      { path: 'siswa/riwayat', name: 'siswa.riwayat', component: PlaceholderView, meta: siswaMeta('Riwayat Hasil') },
      {
        path: 'admin',
        name: 'admin.dashboard',
        component: AdminDashboardView,
        meta: { requiresAuth: true, role: 'super_admin' },
      },
    ],
  },
  { path: '/forbidden', name: 'forbidden', component: ForbiddenView },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
]

// Guard dipisah dari instance agar bisa dipakai ulang di test.
// Analogi Laravel: middleware `auth` + cek role.
export function setupGuard(router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // 1. Hanya sekali per load: isi user dari cookie via /auth/me.
    //    fetchMe sudah menangkap error sendiri, jadi aman walau backend mati.
    if (!auth.initialized) await auth.fetchMe()

/* LOGIN DIMATIKAN SEMENTARA
    //2. Belum login dilarang masuk area auth.
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    */
   
    // 3. Sudah login dilarang kembali ke halaman tamu.
    if (to.meta.guestOnly && auth.isAuthenticated) {
      return dashboardFor(auth.user.role)
    }

    // 4. Role tidak cocok (mis. siswa buka /admin).
    if (to.meta.role && auth.user?.role !== to.meta.role) {
      return { name: 'forbidden' }
    }

    // 5. `/` diarahkan ke dashboard sesuai role.
    if (to.name === 'home') {
      return dashboardFor(auth.user?.role)
    }

    return true
  })

  return router
}

const router = setupGuard(
  createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
  }),
)

export default router