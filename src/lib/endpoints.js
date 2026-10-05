// Satu-satunya tempat daftar path API backend.
// Ganti base URL di .env; ganti path di sini (satu tempat).
// Aturan: stores/views dilarang menulis string path manual.
export const ENDPOINTS = {
  auth: {
    register: '/auth/register',
    login: '/auth/login',
    logout: '/auth/logout',
    me: '/auth/me',
    profile: '/auth/profile',
  },
  siswa: {
    dashboard: '/siswa/dashboard',
  },
  admin: {
    ping: '/admin/ping',
  },
}
