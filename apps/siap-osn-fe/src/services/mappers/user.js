// Bentuk user versi FE: { id, nama, email, role, kodeReferal, jumlahUndang, created_at }.
// Backend Laravel mengirim UserResource: { id, name, email, roles[], kode_referal,
// jumlah_diundang, ... }. Semua konversi terpusat di sini (dipakai services + stores).

// Spatie role -> role FE. Tak dikenal -> null (guard menolak masuk).
export function mapRole(roles) {
  const list = Array.isArray(roles) ? roles : []
  if (list.includes('Super Admin')) return 'super_admin'
  if (list.includes('siswa')) return 'siswa'
  return null
}

export function mapUser(r) {
  if (!r) return null
  return {
    id: r.id,
    nama: r.nama ?? r.name ?? '',
    email: r.email ?? '',
    role: r.role ?? mapRole(r.roles),
    // Kode referal tampil di profil siswa; jumlah undangan hanya akurat bila
    // backend memakai loadCount('diundang') — tanpa itu dianggap nol.
    kodeReferal: r.kode_referal ?? null,
    jumlahUndang: Number.isFinite(Number(r.jumlah_diundang)) ? Number(r.jumlah_diundang) : 0,
    created_at: r.created_at ?? null,
  }
}
