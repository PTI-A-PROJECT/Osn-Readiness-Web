// Penanda opsional dalam field kode/konteks lama: [[PROGRAM:1]], [[PROGRAM:2]], dst.
// Tidak mengubah kontrak JSON maupun data soal tanpa penanda.
export function pisahkanProgram(teks) {
  const sumber = String(teks ?? '').replace(/\r\n?/g, '\n').trim()
  if (!sumber) return []

  const penanda = /^\s*\[\[PROGRAM:(\d+)\]\][ \t]*$/gm
  const cocok = [...sumber.matchAll(penanda)]
  if (!cocok.length) return [{ judul: 'Kode Program', kode: sumber }]

  // Penanda hanya sah jika seluruh isi dimulai dengan penanda pertama.
  // Konten dengan penanda rusak tidak boleh dihilangkan diam-diam.
  if (sumber.slice(0, cocok[0].index).trim()) {
    return [{ judul: 'Kode Program', kode: sumber }]
  }

  const blok = cocok.map((tanda, indeks) => ({
    judul: `Program ${tanda[1]}`,
    kode: sumber.slice(
      tanda.index + tanda[0].length,
      cocok[indeks + 1]?.index ?? sumber.length,
    ).trim(),
  }))

  if (blok.some((item) => !item.kode) || new Set(blok.map((item) => item.judul)).size !== blok.length) {
    return [{ judul: 'Kode Program', kode: sumber }]
  }
  return blok
}
