// Alur OAuth Google berakhir di halaman /oauth/google/selesai dengan
// fragmen #code=... atau #error=... Modul ini satu-satunya tempat yang
// tahu bentuk URL dan arti kode kegagalannya, supaya view tetap remeh.

// Route redirect backend ada di route `web` (di luar /api), memakai sesi
// untuk menyimpan state. Origin diambil dari VITE_API_BASE_URL agar tanpa
// env tambahan.
export function urlRedirectGoogle() {
  const base = new URL(import.meta.env.VITE_API_BASE_URL)
  return `${base.origin}/auth/google/redirect`
}

// Peta kode kegagalan dari backend GoogleOAuthService#alihkanKeGagal.
// Setiap pesan menyebut langkah berikutnya yang bisa dilakukan orang.
export const PESAN_GOOGLE_GAGAL = {
  google_batal: 'Izin Google dibatalkan sebelum selesai.',
  google_state: 'Sesi Google kedaluwarsa. Mulai lagi dari tombol Google.',
  google_gagal: 'Google tidak merespons seperti biasa. Coba lagi beberapa saat.',
  // Akun lama tidak digabung diam-diam: pemiliknya menautkan sendiri.
  google_taut: 'Email Google ini sudah terpakai akun lain. Masuk dengan kata sandi, lalu tautkan Google di profil.',
  google_admin: 'Akun admin tidak dapat masuk melalui Google.',
  google_nonaktif: 'Akun ini dinonaktifkan. Hubungi pengelola SIAP OSN.',
  google_email_verifikasi: 'Email Google belum diverifikasi oleh Google.',
  google_exchange: 'Kode login tidak sah atau sudah kedaluwarsa. Mulai lagi dari tombol Google.',
  lain: 'Login Google gagal. Masuk dengan email dan kata sandi, atau coba tombol Google lagi.',
}

// Kode error dari POST /auth/google/exchange memakai UPPER_SNAKE (lihat
// KodeGoogleTidakValidException, GoogleAkunAdminException, AkunTidakAktifException).
// Diterjemahkan ke kunci peta di atas agar pesan spesifik tetap tampil.
const KODE_EXCHANGE = {
  KODE_GOOGLE_TIDAK_VALID: 'google_exchange',
  GOOGLE_AKUN_ADMIN: 'google_admin',
  AKUN_TIDAK_AKTIF: 'google_nonaktif',
}

export function pesanExchangeGagal(kodeBackend) {
  const kunci = KODE_EXCHANGE[kodeBackend]
  return kunci ? PESAN_GOOGLE_GAGAL[kunci] : PESAN_GOOGLE_GAGAL.lain
}
