// Cache GET di memori untuk lapisan service: menyatukan request yang sama yang
// sedang berjalan dan menyimpan hasilnya selama TTL.
//
// Aturan pemakaian:
// - Fetcher TIDAK menerima AbortSignal pemanggil. Request dibagi ke beberapa
//   pemanggil, jadi satu pemanggil yang membatalkan tidak boleh menggagalkan
//   yang lain. Store sudah memeriksa `signal.aborted` setelah await.
// - Setiap mutasi sukses (POST/PUT/PATCH/DELETE) memanggil `invalidasi()` dari
//   interceptor di lib/api.js. Hasil final (`permanen`) tidak ikut dibuang
//   karena isinya tidak akan berubah.
// - Logout/login memanggil `kosongkan()` agar data akun lain tidak terbaca.

const entri = new Map() // key -> { nilai, kadaluarsa, permanen, janji }
let generasi = 0

export function ambil(key, fetcher, { ttl = 60_000, permanen = () => false, force = false } = {}) {
  const e = entri.get(key)
  if (!force && e) {
    if (e.janji) return e.janji
    if (e.permanen || e.kadaluarsa > Date.now()) return Promise.resolve(e.nilai)
  }

  const mulai = generasi
  const janji = fetcher()
    .then((nilai) => {
      // Mutasi/logout terjadi saat request berjalan: hasilnya mungkin sudah basi.
      if (mulai === generasi) {
        entri.set(key, {
          nilai,
          kadaluarsa: Date.now() + ttl,
          permanen: permanen(nilai) === true,
        })
      } else if (entri.get(key)?.janji === janji) {
        entri.delete(key)
      }
      return nilai
    })
    .catch((err) => {
      if (entri.get(key)?.janji === janji) entri.delete(key)
      throw err
    })
  entri.set(key, { janji })
  return janji
}

// Buang semua entri kecuali hasil final.
export function invalidasi() {
  generasi += 1
  for (const [key, e] of entri) {
    if (!e.permanen) entri.delete(key)
  }
}

export function kosongkan() {
  generasi += 1
  entri.clear()
}
