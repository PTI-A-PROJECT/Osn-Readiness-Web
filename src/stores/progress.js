import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api.js'

/*
 * Satu sumber data untuk semua angka di dashboard siswa dan streak di sidebar.
 *
 * Kontrak API yang diharapkan (silakan sesuaikan nama field-nya dengan backend):
 *
 *   GET /siswa/dashboard
 *   -> {
 *        data: {
 *          pre_test_selesai: false,
 *          tingkat: null,                 // 'Kabupaten' | 'Provinsi' | null
 *          streak: 0,                     // jumlah hari berturut-turut
 *          materi_selesai: 0,
 *          materi_total: 0,               // materi yang sudah diambil siswa
 *          simulasi_diikuti: 0,
 *          rata_rata_nilai: 0,
 *          kompetensi: [],                // [{ nama, skor, target }]
 *          rekomendasi: [],               // [{ judul, sub, badge }]
 *          riwayat: [],                   // [{ judul, tanggal, nilai }]
 *          tingkatan: [],                 // [{ nama, terbuka }]
 *          hasil_terakhir: null           // { judul, tanggal, benar, salah, durasi_menit }
 *        }
 *      }
 *
 * Siswa baru = semua field kosong/0/null, dan UI otomatis menampilkan kondisi kosong.
 */

const angka = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}
const persen = (v) => Math.min(100, Math.max(0, angka(v)))
const daftar = (v) => (Array.isArray(v) ? v : [])

const TINGKATAN_DEFAULT = [
  { nama: 'Kabupaten', terbuka: false },
  { nama: 'Provinsi', terbuka: false },
]

function kosong() {
  return {
    preTestSelesai: false,
    tingkat: null,
    streak: 0,
    materiSelesai: 0,
    materiTotal: 0,
    simulasiDiikuti: 0,
    rataRataNilai: 0,
    kompetensi: [],
    rekomendasi: [],
    riwayat: [],
    tingkatan: TINGKATAN_DEFAULT,
    hasilTerakhir: null,
  }
}

// Mengubah respons backend (snake_case, bisa kosong/null) jadi bentuk yang aman dipakai UI.
function normalize(raw) {
  const r = raw ?? {}
  const h = r.hasil_terakhir

  const tingkatan = daftar(r.tingkatan).map((t) => ({ nama: t.nama, terbuka: Boolean(t.terbuka) }))

  return {
    preTestSelesai: Boolean(r.pre_test_selesai),
    tingkat: r.tingkat ?? null,
    streak: angka(r.streak),
    materiSelesai: angka(r.materi_selesai),
    materiTotal: angka(r.materi_total),
    simulasiDiikuti: angka(r.simulasi_diikuti),
    rataRataNilai: Math.round(angka(r.rata_rata_nilai)),
    kompetensi: daftar(r.kompetensi).map((k) => ({
      nama: k.nama,
      skor: persen(k.skor),
      target: k.target == null ? null : persen(k.target),
    })),
    rekomendasi: daftar(r.rekomendasi).map((x) => ({
      judul: x.judul,
      sub: x.sub ?? '',
      badge: x.badge ?? '',
    })),
    riwayat: daftar(r.riwayat).map((x) => ({
      judul: x.judul,
      tanggal: x.tanggal ?? null,
      nilai: x.nilai ?? null,
    })),
    tingkatan: tingkatan.length ? tingkatan : TINGKATAN_DEFAULT,
    hasilTerakhir: h
      ? {
          judul: h.judul,
          tanggal: h.tanggal ?? null,
          benar: angka(h.benar),
          salah: angka(h.salah),
          durasiMenit: h.durasi_menit == null ? null : angka(h.durasi_menit),
        }
      : null,
  }
}

export const useProgressStore = defineStore('progress', () => {
  const data = ref(kosong())
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref(false)

  let inflight = null // mencegah request ganda (sidebar + dashboard sama-sama memanggil)
  let generasi = 0 // mencegah respons milik user lama masuk setelah ganti akun

  const progressPersen = computed(() => {
    const { materiSelesai, materiTotal } = data.value
    return materiTotal ? Math.round((materiSelesai / materiTotal) * 100) : 0
  })

  function fetchDashboard() {
    if (inflight) return inflight

    const saatIni = generasi
    loading.value = true
    error.value = false

    inflight = api
      .get('/siswa/dashboard')
      .then(({ data: res }) => {
        if (saatIni !== generasi) return
        data.value = normalize(res.data)
        loaded.value = true
      })
      .catch(() => {
        // Data lama dibiarkan agar tampilan tidak salah menunjukkan "belum ada progres".
        if (saatIni === generasi) error.value = true
      })
      .finally(() => {
        if (saatIni === generasi) {
          loading.value = false
          inflight = null
        }
      })

    return inflight
  }

  // Wajib dipanggil saat login/logout supaya data siswa sebelumnya tidak terlihat akun berikutnya.
  function $reset() {
    generasi++
    inflight = null
    data.value = kosong()
    loading.value = false
    loaded.value = false
    error.value = false
  }

  return { data, loading, loaded, error, progressPersen, fetchDashboard, $reset }
})
