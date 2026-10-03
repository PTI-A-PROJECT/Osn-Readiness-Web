import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'
import LandingPage from '@/views/LandingPage.vue'

// Landing di-bundle langsung (halaman pertama mayoritas pengunjung).
// Sisanya lazy: tiap halaman jadi chunk sendiri dan baru diunduh saat dibuka,
// jadi bundle awal tidak ikut membawa dashboard admin/siswa.
const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const AppLayout = () => import('@/layouts/AppLayout.vue')
const LoginView = () => import('@/views/LoginView.vue')
const RegisterView = () => import('@/views/RegisterView.vue')
const SiswaDashboardView = () => import('@/views/siswa/DashboardView.vue')
const SiswaProfileView = () => import('@/views/siswa/ProfileView.vue')
const PlaceholderView = () => import('@/views/siswa/PlaceholderView.vue')
const AdminDashboardView = () => import('@/views/admin/DashboardView.vue')
const ForbiddenView = () => import('@/views/ForbiddenView.vue')
const NotFoundView = () => import('@/views/NotFoundView.vue')

// Satu-satunya tempat yang tahu pemetaan role -> dashboard.
// Dipakai guard di bawah dan LoginView setelah login sukses.
export function dashboardFor(role) {
  return role === 'super_admin' ? { name: 'admin.dashboard' } : { name: 'siswa.dashboard' }
}

// Diekspor agar guard.spec.js bisa membuat router sendiri
// dengan createMemoryHistory dari definisi route yang sama.
export const routes = [
  // Landing page publik, tanpa layout
  { path: '/', name: 'landing', component: LandingPage },

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
      // route 'home' dihapus
      {
        path: 'siswa',
        name: 'siswa.dashboard',
        component: SiswaDashboardView,
        meta: { requiresAuth: true, role: 'siswa' },
      },
      {
        path: 'siswa/profil',
        name: 'siswa.profil',
        component: SiswaProfileView,
        meta: { requiresAuth: true, role: 'siswa' },
      },
      {
        path: 'siswa/pemetaan',
        name: 'siswa.pemetaan',
        component: PlaceholderView,
        meta: { requiresAuth: true, role: 'siswa', title: 'Pemetaan Kompetensi' },
      },
      {
        path: 'siswa/materi',
        name: 'siswa.materi',
        component: PlaceholderView,
        meta: { requiresAuth: true, role: 'siswa', title: 'Materi' },
      },
      {
        path: 'siswa/progress',
        name: 'siswa.progress',
        component: PlaceholderView,
        meta: { requiresAuth: true, role: 'siswa', title: 'Progress Belajar' },
      },
      {
        path: 'siswa/simulasi',
        name: 'siswa.simulasi',
        component: PlaceholderView,
        meta: { requiresAuth: true, role: 'siswa', title: 'Simulasi Seleksi' },
      },
      {
        path: 'siswa/riwayat',
        name: 'siswa.riwayat',
        component: PlaceholderView,
        meta: { requiresAuth: true, role: 'siswa', title: 'Riwayat Hasil' },
      },
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

    if (!auth.initialized) await auth.fetchMe()

    const loggedIn = !!auth.user

    if (to.meta.requiresAuth && !loggedIn) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.guestOnly && loggedIn) {
      return dashboardFor(auth.user.role)
    }

    if (to.meta.role && auth.user?.role !== to.meta.role) {
      return { name: 'forbidden' }
    }

    if (to.name === 'landing' && loggedIn) {
      return dashboardFor(auth.user.role)
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
