// Cara membaca error backend terpusat di sini (jangan parsing di view).
//
// - pesanError: pesan umum dari envelope ({ message }) atau fallback.
// - pesanField: pesan validasi per field. Laravel: 422 +
//   { errors: { email: [...] } } (pesan Inggris) -> diterjemahkan ke
//   Bahasa Indonesia bila ada padanannya. Backend Go lama: 400 +
//   { message: "Email sudah terdaftar" }.
//   Kembali null bila bukan error field yang diminta.
const TERJEMAHAN_FIELD = {
  'The email has already been taken.': 'Email sudah terdaftar',
}

export function pesanError(err, fallback = 'Terjadi kesalahan, coba lagi') {
  return err?.response?.data?.message ?? fallback
}

export function pesanField(err, field) {
  const status = err?.response?.status
  if (status !== 400 && status !== 422) return null
  const dariLaravel = err?.response?.data?.errors?.[field]?.[0]
  if (dariLaravel) return TERJEMAHAN_FIELD[dariLaravel] ?? dariLaravel
  if (field === 'email' && err?.response?.data?.message === 'Email sudah terdaftar') {
    return 'Email sudah terdaftar'
  }
  return null
}
