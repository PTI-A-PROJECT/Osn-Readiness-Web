import { api, TOKEN_KEY } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { mapUser } from '@/services/mappers/user.js'
import { unwrap } from '@/services/envelope.js'

// Token hidup di service (bukan store/lib): store menyimpan salinan
// reaktifnya saja. Satu-satunya pembaca/penulis localStorage token.
export const authService = {
  tokenTersimpan() {
    return localStorage.getItem(TOKEN_KEY)
  },

  simpanToken(t) {
    if (t) localStorage.setItem(TOKEN_KEY, t)
    else localStorage.removeItem(TOKEN_KEY)
  },

  // Satu fungsi = satu endpoint. Masuk & keluar bentuk FE (nama, role);
  // konversi Laravel (name, password_confirmation, UserResource) di sini.
  // GET /auth/me -> { message, data: user }
  async me() {
    return mapUser(unwrap(await api.get(ENDPOINTS.auth.me)))
  },

  // POST /auth/login {email, password} atau {nim, password} -> { message, data: {user, token} }
  async login({ email, password }) {
    const id = String(email ?? '').trim()
    const body = /^\d{8,20}$/.test(id) ? { nim: id, password } : { email, password }
    const { user, token } = unwrap(await api.post(ENDPOINTS.auth.login, body))
    return { user: mapUser(user), token }
  },
  // POST /auth/google/exchange {kode} -> { message, data: {user, token} }
  // Kode sekali pakai dari fragmen callback; dikirim lewat body supaya
  // tidak pernah masuk URL yang muncul di log.
  async exchangeGoogle(kode) {
    const { user, token, perlu_kode_referal } = unwrap(
      await api.post(ENDPOINTS.auth.googleExchange, { kode }),
    )
    return { user: mapUser(user), token, perluKodeReferal: perlu_kode_referal === true }
  },

  // POST /auth/referal {kode_referal} -> { message, data: { user } }
  // Kosong/null = lewati. Hanya berhasil sekali per akun.
  async simpanReferal(kodeReferal) {
    const kode = typeof kodeReferal === 'string' ? kodeReferal.trim() : ''
    const { user } = unwrap(await api.post(ENDPOINTS.auth.referal, { kode_referal: kode || null }))
    return mapUser(user)
  },

  // Laravel wajib password_confirmation; FE memakai nama field `konfirmasi`.
  // Kode referal opsional: hanya dikirim bila diisi, biar payload bersih.
  async register({ nama, email, password, konfirmasi, kodeReferal }) {
    const kode = typeof kodeReferal === 'string' ? kodeReferal.trim() : ''
    const { user, token } = unwrap(
      await api.post(ENDPOINTS.auth.register, {
        name: nama,
        email,
        password,
        password_confirmation: konfirmasi,
        ...(kode ? { kode_referal: kode } : {}),
      }),
    )
    return { user: mapUser(user), token }
  },

  // PUT /auth/profile {name, email} -> { message, data: user }
  async updateProfile({ nama, email }) {
    return mapUser(unwrap(await api.put(ENDPOINTS.auth.profile, { name: nama, email })))
  },

  // PUT /auth/password {current_password, password, password_confirmation}
  async ubahSandi({ current, baru, konfirmasi }) {
    return unwrap(
      await api.put(ENDPOINTS.auth.password, {
        current_password: current,
        password: baru,
        password_confirmation: konfirmasi,
      }),
    )
  },

  async forgotPassword(email) {
    const { data } = await api.post(ENDPOINTS.auth.forgotPassword, { email: email.trim() })
    return data
  },

  async resetPassword({ email, token, password, konfirmasi }) {
    const { data } = await api.post(ENDPOINTS.auth.resetPassword, {
      email, token, password, password_confirmation: konfirmasi,
    })
    return data
  },

  async logout() {
    await api.post(ENDPOINTS.auth.logout)
  },
}
