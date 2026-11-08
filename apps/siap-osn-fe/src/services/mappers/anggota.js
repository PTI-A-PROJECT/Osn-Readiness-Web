const angka = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0)

export function mapRingkasanAnggota(r) {
  return {
    jumlahReferal: angka(r?.jumlah_referal),
    peringkat: angka(r?.peringkat),
    jumlahAnggota: angka(r?.jumlah_anggota),
    totalReferal: angka(r?.total_referal),
  }
}
