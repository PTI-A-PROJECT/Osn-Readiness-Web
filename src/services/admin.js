import { api } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { unwrap } from '@/services/envelope.js'

export const adminService = {
  // GET /admin/ping -> { message, data: { message: "pong" } }
  async ping() {
    return unwrap(await api.get(ENDPOINTS.admin.ping))?.message
  },
}
