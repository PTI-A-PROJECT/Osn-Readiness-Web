import { describe, expect, it } from 'vitest'
import { pisahkanProgram } from './multiCode.js'

describe('pisahkanProgram', () => {
  it('tetap menampilkan kode lama sebagai satu blok', () => {
    expect(pisahkanProgram('begin\\n  writeln(1);\\nend;')).toEqual([
      { judul: 'Kode Program', kode: 'begin\\n  writeln(1);\\nend;' },
    ])
  })

  it('memisahkan dua program tanpa menampilkan penandanya', () => {
    expect(pisahkanProgram('[[PROGRAM:1]]\\npop();\\n[[PROGRAM:2]]\\nfor i := 1 to 3 do writeln(i);')).toEqual([
      { judul: 'Program 1', kode: 'pop();' },
      { judul: 'Program 2', kode: 'for i := 1 to 3 do writeln(i);' },
    ])
  })

  it('mendukung tiga kode dan format CRLF', () => {
    expect(pisahkanProgram('[[PROGRAM:1]]\\r\\na\\r\\n[[PROGRAM:2]]\\r\\nb\\r\\n[[PROGRAM:3]]\\r\\nc')).toHaveLength(3)
  })

  it('tidak membuang penanda atau kode jika format rusak', () => {
    const rusak = '[[PROGRAM:1]]\\nfoo\\n[[PROGRAM:2]]'
    expect(pisahkanProgram(rusak)).toEqual([{ judul: 'Kode Program', kode: rusak }])
  })

  it('tidak membuat blok pada soal tanpa kode', () => {
    expect(pisahkanProgram(null)).toEqual([])
  })
})
