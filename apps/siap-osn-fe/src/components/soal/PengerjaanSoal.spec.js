// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PengerjaanSoal from './PengerjaanSoal.vue'
import { mapSoal } from '@/services/mappers/pretest.js'

vi.mock('@/components/UserMenu.vue', () => ({ default: { template: '<div />' } }))

// Fixture dari JSON siap impor yang sama dengan data Laravel.
// Tidak mengakses API, membuat sesi, atau menulis ke database.
function ambilDariImpor(tahun, idSumber) {
  const lokasi = new URL(
    '../../../../data-analytics/soal/impor/soal_kab_' + tahun + '.json',
    import.meta.url,
  )
  const data = JSON.parse(readFileSync(lokasi, 'utf8'))
  const baris = data.find((butir) => butir.id_sumber === idSumber)
  if (!baris) throw new Error('Soal tidak ditemukan: ' + idSumber)

  return mapSoal({
    id: tahun === 2024 ? 838 : 383,
    materi_id: 29,
    level: baris.tingkat_kesulitan,
    tipe_soal: Object.keys(baris.pilihan ?? {}).length ? 'pilihan_ganda' : 'isian',
    pertanyaan: baris.soal,
    pilihan_jawaban: baris.pilihan,
    kode: baris.kode ?? null,
    konteks: baris.deskripsi_soal
      ? { judul: 'Konteks Soal', isi_konteks: baris.deskripsi_soal }
      : null,
  })
}

function render(soal) {
  return mount(PengerjaanSoal, {
    props: {
      soal: [soal],
      aktif: 0,
      jawaban: {},
      ragu: {},
      tampilkanTimer: false,
    },
  })
}

describe('PengerjaanSoal - data bank soal nyata tanpa sesi', () => {
  it('ID 838: dua fungsi C++ dalam satu editor, tanpa duplikat', () => {
    const wrapper = render(ambilDariImpor(2024, 'kab_soal36_2024'))
    const editor = wrapper.findAll('.program-editor')
    expect(editor).toHaveLength(1)
    expect(editor[0].text()).toContain('int panas(int X)')
    expect(editor[0].text()).toContain('int dingin(int X, int Y)')
    expect(wrapper.find('.konten-konteks').text()).toContain('Perhatikan potongan kode program berikut!')
    expect(wrapper.find('.konten-konteks').text()).not.toContain('int panas(')
    expect(wrapper.find('.pertanyaan').text()).toContain('dingin(2,35)')
    expect(wrapper.emitted('kumpulkan')).toBeUndefined()
  })

  it('ID 383: dua marker dirender sebagai dua editor', () => {
    const wrapper = render(ambilDariImpor(2013, 'kab_soal36_2013'))
    const editor = wrapper.findAll('.program-editor')
    expect(editor).toHaveLength(2)
    expect(editor[0].text()).toContain('Program 1')
    expect(editor[0].text()).toContain('pop();')
    expect(editor[1].text()).toContain('Program 2')
    expect(editor[1].text()).toContain('if (pop() <> -1)')
    expect(wrapper.text()).not.toContain('[[PROGRAM:')
  })
})
