import { api } from '@/lib/api.js'
import { ENDPOINTS } from '@/lib/endpoints.js'
import { unwrap } from '@/services/envelope.js'

export const adminService = {
  // GET /admin/dashboard -> { message, data: { ...ringkasan admin } }
  // Dipakai sebagai ping nyata: endpointnya benar-benar ada di backend
  // (Super Admin) dan butuh token yang valid.
  async dashboard() {
    return unwrap(await api.get(ENDPOINTS.admin.dashboard))
  },
}
