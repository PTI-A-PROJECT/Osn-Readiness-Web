<script setup>
import { computed, ref, watch } from 'vue'
import UserMenu from '@/components/UserMenu.vue'
import { renderKonten } from '@/lib/richtext.js'
import { pisahkanProgram } from '@/lib/multiCode.js'

const props = defineProps({
  judul: { type: String, default: 'Pengerjaan Soal' },
  subjudul: { type: String, default: '' },
  soal: { type: Array, default: () => [] },
  aktif: { type: Number, default: 0 },
  jawaban: { type: [Array, Object], default: () => [] },
  ragu: { type: [Array, Object], default: () => [] },
  total: { type: Number, default: 0 },
  timerLabel: { type: String, default: 'Sisa waktu' },
  waktuTampil: { type: String, default: '00:00:00' },
  timerAktif: { type: Boolean, default: true },
  tampilkanTimer: { type: Boolean, default: true },
  mepet: { type: Boolean, default: false },
  simpanError: { type: Boolean, default: false },
  petunjuk: { type: Object, default: null },
  tipeSoal: { type: Array, default: () => [] },
  labelTipe: { type: String, default: 'Pilihan Ganda - Pilih 1' },
  petunjukTipe: { type: String, default: '' },
  tombolSelesai: { type: Boolean, default: false },
  modalJudul: { type: String, default: 'Kumpulkan jawaban?' },
  modalPeringatan: {
    type: String,
    default: 'Pastikan jawabanmu sudah diperiksa sebelum dikumpulkan.',
  },
  bolehKumpulkanKosong: { type: Boolean, default: true },
  mengirim: { type: Boolean, default: false },
})

const emit = defineEmits([
  'pilih-ganda',
  'isi-jawaban',
  'pindah',
  'toggle-ragu',
  'selesai',
  'kumpulkan',
  'kumpulkan-kosong',
])

const petunjukTerbuka = ref(true)
const modalSelesai = ref(false)
const salinBerhasil = ref(null)

const soalAktif = computed(() => props.soal[props.aktif] ?? null)
const totalSoal = computed(() => props.total || props.soal.length)
const opsiSoal = computed(() => soalAktif.value?.opsi ?? [])

watch(soalAktif, () => {
  modalSelesai.value = false
  salinBerhasil.value = null
})

function ambilNilai(data, indeks, fallback = '') {
  if (data == null) return fallback
  return data[indeks] ?? data[String(indeks)] ?? fallback
}

function soalTerisi(i) {
  const v = ambilNilai(props.jawaban, i, '')
  return v != null && String(v).trim() !== ''
}

function nomorSoal(i) {
  return i + 1
}

const ringkasan = computed(() => {
  let terjawab = 0
  let jumlahRagu = 0
  const kosong = []

  props.soal.forEach((_, i) => {
    if (soalTerisi(i)) terjawab++
    else kosong.push(i)

    if (Boolean(ambilNilai(props.ragu, i, false))) jumlahRagu++
  })

  return {
    terjawab,
    ragu: jumlahRagu,
    belum: kosong.length,
    kosong,
  }
})

function kelasNomer(i) {
  if (i === props.aktif) return ['n-open']
  if (Boolean(ambilNilai(props.ragu, i, false))) return ['n-ragu']
  if (soalTerisi(i)) return ['n-done']
  return ['n-empty']
}

function pergiKe(i) {
  modalSelesai.value = false
  emit('pindah', i)
}

function kirim(termasukKosong = false) {
  if (props.mengirim) return

  if (termasukKosong) {
    if (!props.bolehKumpulkanKosong) return
    emit('kumpulkan-kosong')
  } else {
    emit('kumpulkan')
  }

  emit('selesai', { termasukKosong })
  modalSelesai.value = false
}

// ======================================================
// NORMALISASI DAN PEMISAHAN KONTEN SOAL
// ======================================================

function normalisasiBaris(teks) {
  return String(teks ?? '')
    .replace(/\r\n?/g, '\n')
    .trim()
}

function ringkasSpasi(teks) {
  return normalisasiBaris(teks)
    .replace(/\s+/g, ' ')
    .toLowerCase()
}

function ambilKonteks(s) {
  return s?.konteks?.isi ?? s?.deskripsi_soal ?? s?.deskripsi ?? ''
}

function ambilPertanyaan(s) {
  return s?.pertanyaan ?? s?.soal ?? ''
}

const konteksMentah = computed(() =>
  normalisasiBaris(ambilKonteks(soalAktif.value)),
)

const pertanyaanMentah = computed(() =>
  normalisasiBaris(ambilPertanyaan(soalAktif.value)),
)

const kodeEksplisit = computed(() =>
  normalisasiBaris(
    soalAktif.value?.kode ??
    soalAktif.value?.konteks?.kode ??
    '',
  ),
)

// ======================================================
// DETEKSI BLOK KODE MARKDOWN
// ======================================================

function cariFence(teks) {
  const cocok = [
    ...teks.matchAll(/```([\w+.#-]*)[ \t]*\n([\s\S]*?)\n?```/g),
  ]

  if (cocok.length !== 1) return null

  return {
    bahasa: cocok[0][1],
    kode: cocok[0][2].trim(),
    lengkap: cocok[0][0],
  }
}

const konteksFence = computed(() =>
  cariFence(konteksMentah.value),
)

const pertanyaanFence = computed(() =>
  cariFence(pertanyaanMentah.value),
)

// ======================================================
// DETEKSI KODE DARI DATA SOAL LAMA
// ======================================================

function kodeLamaDariKonteks(teks) {
  if (!teks || teks.includes('```')) return ''

  const baris = teks
    .split('\n')
    .map((b) => b.trim())
    .filter(Boolean)

  if (baris.length < 3) return ''

  const pertama = baris[0]

  const pascal =
    /^(?:type|const|function|procedure|program|unit|uses|var|begin)\b/i.test(
      pertama,
    ) &&
    /\b(?:begin|end|for|while|writeln|readln)\b|:=/i.test(teks)

  const cpp =
    /^(?:#include\b|using\s+namespace\b|int\s+main\s*\()/i.test(
      pertama,
    ) &&
    /[{};]|#include/.test(teks)

  const python =
    /^(?:def\s+\w+\s*\(|import\s+\w+|from\s+\w+)/.test(
      pertama,
    ) &&
    /:\s*$|\breturn\b|\bprint\s*\(/m.test(teks)

  return pascal || cpp || python ? teks : ''
}

// ======================================================
// MENENTUKAN KODE PROGRAM
// ======================================================

const kodeProgram = computed(
  () =>
    kodeEksplisit.value ||
    // Saat impor, kode dapat digabung ke dalam konteks soal.
    konteksMentah.value.match(/\[\[PROGRAM:\d+\]\][\s\S]*$/m)?.[0] ||
    konteksFence.value?.kode ||
    pertanyaanFence.value?.kode ||
    kodeLamaDariKonteks(konteksMentah.value) ||
    '',
)

const blokProgram = computed(() => pisahkanProgram(kodeProgram.value))

const bahasaProgram = computed(() => {
  const k = kodeProgram.value

  const hint = String(
    soalAktif.value?.bahasa ??
    konteksFence.value?.bahasa ??
    pertanyaanFence.value?.bahasa ??
    '',
  ).toLowerCase()

  if (
    /cpp|c\+\+|cxx/.test(hint) ||
    /#include\s*[<"]|std::|cout\s*<</.test(k)
  ) {
    return 'C++'
  }

  if (
    /python|\bpy\b/.test(hint) ||
    /^\s*def\s+\w+\s*\(/m.test(k)
  ) {
    return 'Python'
  }

  if (
    /pascal|delphi/.test(hint) ||
    /\b(?:begin|longint|writeln|readln)\b|:=/i.test(k)
  ) {
    return 'Pascal'
  }

  if (/javascript|js/.test(hint)) return 'JavaScript'

  return 'Kode Program'
})

const namaFileKode = computed(
  () =>
    ({
      'C++': 'program.cpp',
      Pascal: 'program.pas',
      Python: 'program.py',
      JavaScript: 'program.js',
    })[bahasaProgram.value] ?? 'program.txt',
)


// ======================================================
// PERBAIKAN UTAMA: HAPUS KODE YANG MUNCUL DUA KALI
// ======================================================

// Escape karakter khusus agar kode aman digunakan sebagai RegExp.
function escapeRegExp(teks) {
  return teks.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// Menghapus kode program dari teks pertanyaan jika kode yang
// sama sudah ditampilkan pada editor program.
//
// Penghapusan hanya dilakukan jika seluruh rangkaian kode
// cocok. Narasi atau kalimat sebelum dan sesudah kode tetap ada.
function tanpaKodeGanda(teks, kode) {
  const sumber = normalisasiBaris(teks)
  const program = normalisasiBaris(kode)

  if (!sumber || !program) return sumber

  // Jika seluruh pertanyaan hanya berisi kode, jangan tampilkan
  // kode lagi di area pertanyaan.
  if (ringkasSpasi(sumber) === ringkasSpasi(program)) {
    return ''
  }

  // Hindari menghapus teks pendek yang kebetulan mirip kode.
  if (program.length < 12) return sumber

  // Ubah kode menjadi pola yang toleran terhadap perbedaan
  // spasi dan pergantian baris.
  const pola = program
    .trim()
    .split(/\s+/)
    .map(escapeRegExp)
    .join('\\s+')

  try {
    const regex = new RegExp(pola)

    if (!regex.test(sumber)) {
      return sumber
    }

    const hasil = sumber.replace(regex, '')

    return normalisasiBaris(
      hasil
        .replace(/[ \t]+/g, ' ')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        .replace(/\n{3,}/g, '\n\n'),
    )
  } catch (error) {
    console.error('Gagal menghapus kode duplikat:', error)
    return sumber
  }
}

// ======================================================
// MENAMPILKAN KONTEKS TANPA KODE DUPLIKAT
// ======================================================

function konteksTanpaKode(teks, kode, fence) {
  const konteks = normalisasiBaris(teks)

  if (!kode) return konteks

  if (ringkasSpasi(konteks) === ringkasSpasi(kode)) {
    return ''
  }

  if (fence?.kode === kode) {
    return normalisasiBaris(
      konteks.replace(fence.lengkap, ''),
    )
  }

  if (konteks.includes(kode)) {
    return normalisasiBaris(
      konteks.replace(kode, ''),
    )
  }

  return konteks
}

const konteksFinal = computed(() => {
  const konteks = konteksTanpaKode(
    konteksMentah.value,
    kodeProgram.value,
    konteksFence.value,
  )

  if (
    ringkasSpasi(konteks) ===
    ringkasSpasi(pertanyaanMentah.value)
  ) {
    return ''
  }

  const pengantar = normalisasiBaris(
    soalAktif.value?.pengantar,
  )

  if (
    pengantar &&
    ringkasSpasi(konteks) === ringkasSpasi(pengantar)
  ) {
    return ''
  }

  return konteks
})

// ======================================================
// PERTANYAAN TANPA KODE YANG SUDAH ADA DI EDITOR
// ======================================================

const pertanyaanFinal = computed(() => {
  const pertanyaan = pertanyaanMentah.value

  if (!pertanyaan) return ''

  // Jika pertanyaan menggunakan fenced code, hapus seluruh
  // blok fenced ketika isinya sama dengan kode editor.
  const fence = pertanyaanFence.value

  if (
    fence?.kode &&
    ringkasSpasi(fence.kode) ===
      ringkasSpasi(kodeProgram.value)
  ) {
    return normalisasiBaris(
      pertanyaan.replace(fence.lengkap, ''),
    )
  }

  // Untuk soal lama yang menyimpan kode sebagai teks biasa,
  // hapus kode dari pertanyaan tetapi pertahankan narasinya.
  return blokProgram.value.reduce(
    (teks, blok) => tanpaKodeGanda(teks, blok.kode),
    pertanyaan,
  )
})

// ======================================================
// RENDER KONTEN
// ======================================================

const pengantarHtml = computed(() => {
  const pengantar = normalisasiBaris(
    soalAktif.value?.pengantar,
  )

  return pengantar ? renderKonten(pengantar) : ''
})

const konteksHtml = computed(() =>
  konteksFinal.value
    ? renderKonten(konteksFinal.value)
    : '',
)

const pertanyaanHtml = computed(() =>
  pertanyaanFinal.value
    ? renderKonten(pertanyaanFinal.value)
    : '',
)

// ======================================================
// JUDUL DAN TAMPILAN KONTEKS
// ======================================================

const judulKonteks = computed(() => {
  const judul = normalisasiBaris(
    soalAktif.value?.konteks?.judul,
  )

  if (!judul || judul === 'Konteks Soal') return ''

  if (
    kodeProgram.value &&
    ringkasSpasi(judul) ===
      ringkasSpasi(kodeProgram.value)
  ) {
    return ''
  }

  if (
    ringkasSpasi(judul) ===
    ringkasSpasi(konteksMentah.value)
  ) {
    return ''
  }

  // Judul lama berisi program tidak perlu ditampilkan.
  if (
    /^(?:type|const|function|procedure|program|unit|uses|var|begin)\b/i.test(
      judul,
    ) &&
    (/\bbegin\b/i.test(judul) || /:=/.test(judul))
  ) {
    return ''
  }

  return judul
})

const tampilKonteks = computed(() =>
  Boolean(
    konteksHtml.value ||
    soalAktif.value?.konteks?.gambar ||
    judulKonteks.value,
  ),
)

// ======================================================
// SALIN KODE PROGRAM
// ======================================================

async function salinKode(blok, indeks) {
  try {
    await navigator.clipboard.writeText(blok.kode)
    salinBerhasil.value = indeks
  } catch (error) {
    console.error('Tidak dapat menyalin kode:', error)
  }
}
</script>

<template>
  <div class="kerja">
    <header class="header">
      <div class="header-left">
        <h1>{{ judul }}</h1>
        <p>{{ subjudul }}</p>
      </div>

      <div class="header-right">
        <span v-if="tampilkanTimer" class="timer-label">{{ timerLabel }}</span>

        <span
          v-if="tampilkanTimer"
          class="timer"
          :class="{ 'timer-mepet': timerAktif && mepet }"
        >
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <circle cx="12" cy="13" r="8" />
            <path d="M12 9v4l2 2M9 2h6" />
          </svg>
          {{ waktuTampil }}
        </span>

        <span v-if="simpanError" class="belum-tersimpan">
          Gagal menyimpan, periksa koneksi
        </span>

        <UserMenu />
      </div>
    </header>

    <main class="main">
      <section class="col-left">
        <div
          v-if="petunjuk && petunjukTerbuka"
          class="card petunjuk"
        >
          <div class="petunjuk-head">
            <h2>{{ petunjuk.judul }}</h2>

            <button
              class="close"
              type="button"
              aria-label="Tutup petunjuk"
              @click="petunjukTerbuka = false"
            >
              ✕
            </button>
          </div>

          <p class="petunjuk-desc">
            {{ petunjuk.isi }}
          </p>

          <div class="tipe-grid">
            <div
              v-for="t in tipeSoal.length ? tipeSoal : (petunjuk.tipe ?? [])"
              :key="t.nama"
              class="tipe"
              :class="t.kelas"
            >
              <span class="tipe-label">TIPE SOAL</span>
              <strong>{{ t.nama }}</strong>
              <p>{{ t.desc }}</p>
            </div>
          </div>
        </div>

        <div v-if="soalAktif" class="card soal">
          <div class="soal-meta">
            <div class="soal-meta-left">
              <span class="chip chip-blue">
                Soal {{ aktif + 1 }} dari {{ totalSoal }} soal
              </span>

              <span class="chip chip-gray">
                {{ labelTipe }}
              </span>
            </div>

            <div class="soal-meta-right">
              <span v-if="soalTerisi(aktif)" class="tersimpan">
                ✓ Terjawab
              </span>

              <span class="bobot">
                Bobot {{ soalAktif.bobot ?? 0 }} poin
              </span>
            </div>
          </div>

          <!-- KONTEKS SOAL -->
          <div v-if="tampilKonteks" class="konteks">
            <strong v-if="judulKonteks">
              {{ judulKonteks }}
            </strong>

            <div
              v-if="konteksHtml"
              class="konten-konteks"
              v-html="konteksHtml"
            ></div>

            <img
              v-if="soalAktif.konteks?.gambar"
              :src="soalAktif.konteks.gambar"
              alt="Gambar konteks soal"
            />
          </div>

          <!-- GAMBAR SOAL -->
          <img
            v-if="soalAktif.gambar"
            :src="soalAktif.gambar"
            class="gambar-soal"
            alt="Gambar soal"
          />

          <!-- PENGANTAR SOAL -->
          <div
            v-if="pengantarHtml"
            class="pengantar-soal"
            v-html="pengantarHtml"
          ></div>

          <!-- EDITOR KODE PROGRAM -->
          <div
            v-for="(blok, indeksBlok) in blokProgram"
            :key="indeksBlok"
            class="program-editor"
          >
            <div class="program-header">
              <div class="program-header-left">
                <span class="program-dot"></span>
                <span class="program-dot"></span>
                <span class="program-dot"></span>

                <span class="program-filename">
                  {{ blokProgram.length > 1 ? blok.judul : namaFileKode }}
                </span>
              </div>

              <div class="program-header-right">
                <span>{{ bahasaProgram }}</span>

                <button
                  type="button"
                  class="program-copy"
                  @click="salinKode(blok, indeksBlok)"
                >
                  {{ salinBerhasil === indeksBlok ? '✓ Disalin' : '▢ Salin' }}
                </button>
              </div>
            </div>

            <div
              class="program-body"
              role="region"
              :aria-label="blokProgram.length > 1 ? blok.judul : 'Potongan kode program'"
              tabindex="0"
            >
              <div
                v-for="(baris, index) in blok.kode.split('\n')"
                :key="index"
                class="program-line"
              >
                <span
                  class="program-line-number"
                  aria-hidden="true"
                >
                  {{ index + 1 }}
                </span>

                <code class="program-line-code">
                  {{ baris || ' ' }}
                </code>
              </div>
            </div>
          </div>

          <!-- PERTANYAAN SOAL -->
          <div
            v-if="pertanyaanHtml"
            class="pertanyaan"
            v-html="pertanyaanHtml"
          ></div>

          <p v-if="petunjukTipe" class="hint">
            {{ petunjukTipe }}
          </p>

          <!-- PILIHAN JAWABAN -->
          <div
            v-if="soalAktif.tipe === 'ganda'"
            class="opsi-list"
          >
            <button
              v-for="o in opsiSoal"
              :key="o.kode"
              type="button"
              class="opsi"
              :class="{
                aktif:
                  String(ambilNilai(jawaban, aktif, '')).toUpperCase() ===
                  String(o.kode).toUpperCase(),
              }"
              @click="emit('pilih-ganda', o.kode)"
            >
              <span class="radio">
                <span
                  v-if="
                    String(ambilNilai(jawaban, aktif, '')).toUpperCase() ===
                    String(o.kode).toUpperCase()
                  "
                  class="dot"
                ></span>
              </span>

              <b>{{ o.kode }}.</b>

              <span
                class="opsi-teks"
                v-html="renderKonten(String(o.teks ?? ''))"
              ></span>
            </button>
          </div>

          <!-- JAWABAN URAIAN -->
          <div v-else class="opsi-list">
            <textarea
              class="uraian"
              rows="7"
              placeholder="Tulis jawabanmu di sini..."
              :value="ambilNilai(jawaban, aktif, '') || ''"
              @input="emit('isi-jawaban', $event.target.value)"
            ></textarea>
          </div>

          <!-- NAVIGASI SOAL -->
          <div class="nav">
            <button
              class="btn btn-prev"
              type="button"
              :disabled="aktif === 0"
              @click="emit('pindah', aktif - 1)"
            >
              ← Sebelumnya
            </button>

            <button
              class="btn btn-ragu"
              type="button"
              @click="emit('toggle-ragu')"
            >
              <span
                class="cb"
                :class="{
                  on: Boolean(ambilNilai(ragu, aktif, false)),
                }"
              >
                {{
                  ambilNilai(ragu, aktif, false) ? '✓' : ''
                }}
              </span>
              Ragu
            </button>

            <button
              v-if="tombolSelesai"
              class="btn btn-next"
              type="button"
              @click="modalSelesai = true"
            >
              Selesai →
            </button>

            <button
              v-else
              class="btn btn-next"
              type="button"
              :disabled="aktif >= totalSoal - 1"
              @click="emit('pindah', aktif + 1)"
            >
              Berikutnya →
            </button>
          </div>
        </div>
      </section>

      <!-- RINGKASAN STATUS SOAL -->
      <aside class="col-right">
        <div class="card ringkasan">
          <div class="ringkasan-head">
            <h2>RINGKASAN STATUS SOAL</h2>

            <span class="badge">
              {{ totalSoal }} Butir
            </span>
          </div>

          <div class="stat-grid">
            <div class="stat stat-green">
              <b>{{ ringkasan.terjawab }}</b>
              <span>Terjawab</span>
            </div>

            <div class="stat stat-orange">
              <b>{{ ringkasan.ragu }}</b>
              <span>Ragu-ragu</span>
            </div>

            <div class="stat stat-gray">
              <b>{{ ringkasan.belum }}</b>
              <span>Belum</span>
            </div>
          </div>

          <p class="nomer-label">Nomor Soal:</p>

          <div class="nomer-grid">
            <button
              v-for="(s, i) in soal"
              :key="s.id ?? i"
              class="nomer"
              :class="kelasNomer(i)"
              type="button"
              @click="emit('pindah', i)"
            >
              {{ nomorSoal(i) }}
            </button>
          </div>

          <div class="legend">
            <span>
              <i class="lg lg-green"></i>
              Terjawab ({{ ringkasan.terjawab }})
            </span>

            <span>
              <i class="lg lg-yellow"></i>
              Ragu-ragu ({{ ringkasan.ragu }})
            </span>

            <span>
              <i class="lg lg-open"></i>
              Sedang dibuka
            </span>

            <span>
              <i class="lg lg-empty"></i>
              Belum ({{ ringkasan.belum }})
            </span>
          </div>
        </div>
      </aside>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-inner">
        <span>© 2026 SIAP OSN. Seluruh hak cipta dilindungi.</span>
        <span>Malang, Indonesia</span>
      </div>
    </footer>

    <!-- MODAL SELESAI -->
    <div
      v-if="modalSelesai"
      class="overlay"
      @click.self="!mengirim && (modalSelesai = false)"
    >
      <div
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-label="Konfirmasi pengumpulan jawaban"
      >
        <div class="modal-head">
          <span class="modal-icon">?</span>

          <div>
            <h3>{{ modalJudul }}</h3>

            <p>
              Kamu masih memiliki {{ ringkasan.belum }} soal kosong
              dan {{ ringkasan.ragu }} soal yang ditandai ragu.
            </p>
          </div>
        </div>

        <div class="modal-stats">
          <div class="mstat mstat-green">
            <b>{{ ringkasan.terjawab }}</b>
            <span>Terjawab</span>
          </div>

          <div class="mstat mstat-orange">
            <b>{{ ringkasan.ragu }}</b>
            <span>Ragu-ragu</span>
          </div>

          <div class="mstat mstat-gray">
            <b>{{ ringkasan.belum }}</b>
            <span>Belum</span>
          </div>
        </div>

        <div
          v-if="ringkasan.belum"
          class="modal-kosong"
        >
          <span class="modal-kosong-label">
            Soal kosong:
          </span>

          <button
            v-for="i in ringkasan.kosong"
            :key="i"
            class="modal-kosong-nomor"
            type="button"
            @click="pergiKe(i)"
          >
            {{ nomorSoal(i) }}
          </button>
        </div>

        <div class="modal-warning">
          {{ modalPeringatan }}
        </div>

        <div class="modal-actions">
          <button
            class="mbtn mbtn-yellow"
            type="button"
            :disabled="mengirim"
            @click="modalSelesai = false"
          >
            Kembali Mengerjakan
          </button>

          <button
            v-if="ringkasan.belum"
            class="mbtn mbtn-ghost"
            type="button"
            :disabled="!bolehKumpulkanKosong || mengirim"
            @click="kirim(true)"
          >
            {{
              mengirim
                ? 'Mengumpulkan…'
                : 'Kumpulkan yang Kosong'
            }}
          </button>

          <button
            v-else
            class="mbtn mbtn-navy"
            type="button"
            :disabled="mengirim"
            @click="kirim(false)"
          >
            {{
              mengirim
                ? 'Mengumpulkan…'
                : 'Kumpulkan Jawaban'
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pengantar-soal {
  margin: 20px 0 12px;
  color: #334155;
  font-size: 14px;
  line-height: 1.8;
}
.pengantar-soal :deep(p) {
  margin: 0;
}
.kerja {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  min-height: 100%;
  color: #172033;
  background: #f5f7fb;
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif;
  -webkit-font-smoothing: antialiased;
}
.kerja *,
.kerja *::before,
.kerja *::after {
  box-sizing: border-box;
}
button,
textarea {
  font: inherit;
}
button {
  cursor: pointer;
}
button:focus-visible,
textarea:focus-visible {
  outline: 3px solid rgb(59 130 246 / 35%);
  outline-offset: 2px;
}
.header {
  position: relative;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 18px 30px;
  border-bottom: 1px solid #e5eaf2;
  background: #fff;
}
.header-left {
  min-width: 0;
}
.header h1 {
  margin: 0;
  color: #172033;
  font-size: 21px;
  font-weight: 750;
  letter-spacing: -0.4px;
}
.header p {
  margin: 5px 0 0;
  color: #7b879b;
  font-size: 13px;
}
.header-right {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 11px;
}
.timer-label {
  color: #778198;
  font-size: 13px;
}
.timer {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 37px;
  padding: 8px 13px;
  border: 1px solid #fecdd3;
  border-radius: 9px;
  background: #fff1f2;
  color: #be123c;
  font-size: 13px;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.timer-mepet {
  border-color: #fdba74;
  background: #fff7ed;
  color: #c2410c;
  animation: timer-pulse 1.5s infinite;
}
.belum-tersimpan {
  max-width: 180px;
  color: #b45309;
  font-size: 12px;
}
@keyframes timer-pulse {
  50% {
    opacity: 0.72;
  }
}
.main {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 350px;
  align-items: start;
  gap: 22px;
  width: 100%;
  max-width: 1500px;
  margin: 0 auto;
  padding: 28px 30px 36px;
}
.col-left {
  display: flex;
  flex-direction: column;
  gap: 22px;
  min-width: 0;
}
.col-right {
  position: sticky;
  top: 20px;
  min-width: 0;
}
.card {
  min-width: 0;
  border: 1px solid #e5eaf2;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 3px 12px rgb(15 23 42 / 3%);
}
.petunjuk {
  padding: 24px;
  border-color: #f3d6a8;
  background: #fffdf8;
}
.petunjuk-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.petunjuk h2 {
  margin: 0;
  color: #78350f;
  font-size: 17px;
  font-weight: 750;
}
.close {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #9a3412;
  font-size: 17px;
}
.close:hover {
  background: #ffedd5;
}
.petunjuk-desc {
  margin: 12px 0 20px;
  color: #57534e;
  font-size: 14px;
  line-height: 1.8;
  white-space: pre-line;
}
.tipe-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.tipe {
  min-width: 0;
  padding: 20px 18px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff7ed;
}
.tipe-green {
  background: #dcfce7;
  border-color: #d1fae5;
}
.tipe-yellow {
  background: #fff7ed;
  border-color: #ffedd5;
}
.tipe-red {
  background: #fee2e2;
  border-color: #fecaca;
}
.tipe-label {
  display: block;
  margin-bottom: 7px;
  color: #9a6a35;
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 1px;
}
.tipe strong {
  display: block;
  margin-bottom: 7px;
  color: #78350f;
  font-size: 14px;
}
.tipe p {
  margin: 0;
  color: #78634e;
  font-size: 12px;
  line-height: 1.7;
}
.soal {
  padding: 27px;
}
.soal-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 17px;
  border-bottom: 1px solid #edf0f5;
}
.soal-meta-left,
.soal-meta-right {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  display: inline-flex;
  align-items: center;
  min-height: 27px;
  padding: 5px 10px;
  border-radius: 7px;
  font-size: 11px;
  font-weight: 650;
}
.chip-blue {
  border: 1px solid #d5e3ff;
  background: #edf4ff;
  color: #1d4ed8;
}
.chip-gray {
  border: 1px solid #e6eaf0;
  background: #f5f6f8;
  color: #4b5563;
}
.tersimpan {
  color: #15803d;
  font-size: 12px;
  font-weight: 700;
}
.bobot {
  color: #778198;
  font-size: 12px;
}
.konteks {
  min-width: 0;
  margin: 22px 0 10px;
  padding: 18px;
  overflow: hidden;
  border: 1px solid #d9e5fb;
  border-radius: 12px;
  background: #f7faff;
}
.konteks > strong {
  display: block;
  margin-bottom: 12px;
  color: #1e3a8a;
  font-size: 14px;
}
.konten-konteks {
  min-width: 0;
  color: #334155;
  font-size: 14px;
  line-height: 1.85;
  overflow-wrap: anywhere;
}
.konten-konteks :deep(p) {
  margin: 0 0 12px;
}
.konten-konteks :deep(p:last-child) {
  margin-bottom: 0;
}
.konten-konteks :deep(img),
.pertanyaan :deep(img) {
  display: block;
  max-width: 100%;
  height: auto;
  margin: 14px auto;
  border-radius: 9px;
}
.konten-konteks :deep(ul),
.konten-konteks :deep(ol),
.pertanyaan :deep(ul),
.pertanyaan :deep(ol) {
  padding-left: 24px;
}
.konten-konteks :deep(blockquote) {
  padding: 10px 14px;
  border-left: 3px solid #93b4f8;
  background: #edf4ff;
  color: #475569;
}
.program-editor {
  margin: 20px 0;
  overflow: hidden;
  border: 1px solid #26334c;
  border-radius: 12px;
  background: #0f172a;
}
.program-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 16px;
  border-bottom: 1px solid #253149;
  color: #94a3b8;
  font-family: Consolas, monospace;
  font-size: 12px;
}
.program-header-left,
.program-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.program-dot {
  flex-shrink: 0;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #334155;
}
.program-filename {
  margin-left: 10px;
  color: #cbd5e1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.program-copy {
  padding: 4px 8px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: #94a3b8;
  font-size: 12px;
  white-space: nowrap;
}
.program-copy:hover {
  background: #1e293b;
  color: white;
}
.program-body {
  padding: 16px 12px;
  overflow-x: auto;
  font-family: Consolas, Monaco, 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.9;
  tab-size: 4;
}
.program-line {
  display: flex;
  min-width: max-content;
}
.program-line-number {
  flex-shrink: 0;
  width: 32px;
  padding-right: 12px;
  color: #64748b;
  text-align: right;
  user-select: none;
}
.program-line-code {
  color: #e2e8f0;
  white-space: pre;
  font: inherit;
}
.konten-konteks :deep(pre),
.pertanyaan :deep(pre),
.opsi-teks :deep(pre) {
  max-width: 100%;
  overflow-x: auto;
  margin: 14px 0;
  padding: 16px 18px;
  border: 1px solid #29364b;
  border-radius: 10px;
  background: #111827;
  color: #e5edf8;
  white-space: pre;
  overflow-wrap: normal;
  font:
    400 13px/1.8 Consolas,
    Monaco,
    'Courier New',
    monospace;
}
.konten-konteks :deep(pre code),
.pertanyaan :deep(pre code),
.opsi-teks :deep(pre code) {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  white-space: inherit;
  font: inherit;
}
.konten-konteks :deep(:not(pre) > code),
.pertanyaan :deep(:not(pre) > code),
.opsi-teks :deep(:not(pre) > code) {
  padding: 2px 5px;
  border-radius: 5px;
  background: #edf4ff;
  color: #1e40af;
}
.gambar-soal {
  display: block;
  max-width: 100%;
  height: auto;
  margin: 20px auto;
  border: 1px solid #e5eaf2;
  border-radius: 10px;
  object-fit: contain;
}
.pertanyaan {
  min-width: 0;
  margin: 22px 0 10px;
  color: #172033;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.85;
  overflow-wrap: anywhere;
}
.pertanyaan :deep(p) {
  margin: 0 0 13px;
}
.pertanyaan :deep(p:last-child) {
  margin-bottom: 0;
}
.pertanyaan :deep(table),
.konten-konteks :deep(table) {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
}
.pertanyaan :deep(td),
.pertanyaan :deep(th),
.konten-konteks :deep(td),
.konten-konteks :deep(th) {
  padding: 9px 12px;
  border: 1px solid #dbe2ec;
}
.hint {
  margin: 12px 0 16px;
  color: #8490a4;
  font-size: 12px;
  line-height: 1.6;
}
.opsi-list {
  display: flex;
  flex-direction: column;
  gap: 11px;
  min-width: 0;
  margin-bottom: 24px;
}
.opsi {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  width: 100%;
  min-width: 0;
  min-height: 50px;
  padding: 13px 14px;
  border: 1px solid #e1e7f0;
  border-radius: 10px;
  background: #fff;
  color: #273449;
  text-align: left;
  font-size: 14px;
  line-height: 1.7;
  transition:
    border-color 0.18s,
    background 0.18s;
}
.opsi:hover {
  border-color: #9bb9f6;
  background: #f9fbff;
}
.opsi.aktif {
  border-color: #3974e8;
  background: #f2f6ff;
  box-shadow: 0 0 0 2px rgb(57 116 232 / 9%);
}
.opsi b {
  flex-shrink: 0;
  min-width: 17px;
  color: #1d4ed8;
  font-size: 13px;
}
.radio {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 18px;
  height: 18px;
  margin-top: 2px;
  border: 1.5px solid #cbd5e1;
  border-radius: 50%;
  background: #fff;
}
.opsi.aktif .radio {
  border-color: #2563eb;
  background: #2563eb;
}
.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #fff;
}
.opsi-teks {
  flex: 1;
  min-width: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.opsi-teks :deep(p) {
  margin: 0 0 8px;
}
.opsi-teks :deep(p:last-child) {
  margin: 0;
}
.opsi-teks :deep(img) {
  max-width: 100%;
  height: auto;
}
.uraian {
  display: block;
  width: 100%;
  min-width: 0;
  min-height: 150px;
  padding: 14px 15px;
  border: 1px solid #dce3ed;
  border-radius: 10px;
  background: #fff;
  color: #172033;
  font-size: 14px;
  line-height: 1.8;
  resize: vertical;
}
.uraian:focus {
  border-color: #3974e8;
  box-shadow: 0 0 0 3px rgb(57 116 232 / 10%);
}
.nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 6px;
  padding-top: 22px;
  border-top: 1px solid #edf0f5;
}
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 39px;
  padding: 9px 14px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  transition: background 0.18s;
}
.btn:disabled,
.mbtn:disabled {
  opacity: 0.42;
  cursor: not-allowed;
}
.btn-prev {
  border-color: #dbe2ed;
  background: #fff;
  color: #475569;
}
.btn-ragu {
  border-color: #f4d5a5;
  background: #fff8eb;
  color: #a16207;
}
.btn-next {
  border-color: #1d4ed8;
  background: #1d4ed8;
  color: #fff;
}
.btn-next:not(:disabled):hover {
  background: #1e40af;
}
.cb {
  display: inline-grid;
  place-items: center;
  width: 15px;
  height: 15px;
  border: 1px solid #d6a04a;
  border-radius: 4px;
  background: #fff;
  color: #fff;
  font-size: 10px;
}
.cb.on {
  border-color: #b7791f;
  background: #b7791f;
}
.ringkasan {
  padding: 23px;
}
.ringkasan-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 21px;
}
.ringkasan h2 {
  margin: 0;
  color: #334155;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.4px;
}
.badge {
  flex-shrink: 0;
  padding: 5px 9px;
  border: 1px solid #f3d8a8;
  border-radius: 7px;
  background: #fff7e8;
  color: #a16207;
  font-size: 11px;
  font-weight: 750;
}
.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 0;
  min-height: 76px;
  padding: 10px 5px;
  border: 1px solid;
  border-radius: 10px;
}
.stat b {
  font-size: 21px;
  font-weight: 800;
}
.stat span {
  font-size: 10px;
  font-weight: 650;
  text-align: center;
}
.stat-green {
  border-color: #b5e9c8;
  background: #f0fdf4;
  color: #15803d;
}
.stat-orange {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.stat-gray {
  border-color: #e2e8f0;
  background: #f8fafc;
  color: #475569;
}
.nomer-label {
  margin: 21px 0 12px;
  color: #64748b;
  font-size: 12px;
  font-weight: 650;
}
.nomer-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}
.nomer {
  display: grid;
  place-items: center;
  min-width: 0;
  min-height: 41px;
  padding: 5px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #475569;
  font-size: 12px;
  font-weight: 750;
}
.nomer:hover {
  border-color: #93b4f8;
}
.n-done {
  border-color: #a7e8bd;
  background: #dcfce7;
  color: #166534;
}
.n-ragu {
  border-color: #f5cb91;
  background: #ffedd5;
  color: #9a3412;
}
.n-open {
  border: 2px solid #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
  box-shadow: 0 0 0 2px rgb(37 99 235 / 9%);
}
.n-empty {
  border-color: #e2e8f0;
  background: #fff;
  color: #64748b;
}
.legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 9px;
  margin-top: 21px;
  padding-top: 18px;
  border-top: 1px solid #edf0f5;
}
.legend span {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #64748b;
  font-size: 10px;
}
.lg {
  display: inline-block;
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  border: 1px solid transparent;
  border-radius: 3px;
}
.lg-green {
  border-color: #86efac;
  background: #bbf7d0;
}
.lg-yellow {
  border-color: #fdba74;
  background: #fed7aa;
}
.lg-open {
  border: 2px solid #2563eb;
  background: #fff;
}
.lg-empty {
  border-color: #cbd5e1;
  background: #fff;
}
.footer {
  margin-top: auto;
  padding: 20px 30px;
  background: #101d3a;
}
.footer-inner {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px 20px;
  max-width: 1440px;
  margin: 0 auto;
  color: #a8b4cb;
  font-size: 12px;
  line-height: 1.7;
}
.overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: 20px;
  overflow-y: auto;
  background: rgb(15 23 42 / 58%);
  backdrop-filter: blur(4px);
}
.modal {
  width: 100%;
  max-width: 510px;
  max-height: calc(100vh - 40px);
  padding: 26px;
  overflow-y: auto;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 25px 70px rgb(15 23 42 / 25%);
}
.modal-head {
  display: flex;
  align-items: flex-start;
  gap: 13px;
  margin-bottom: 23px;
}
.modal-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 43px;
  height: 43px;
  border: 1px solid #f5d59a;
  border-radius: 12px;
  background: #fff7e8;
  color: #a16207;
  font-size: 23px;
  font-weight: 800;
}
.modal h3 {
  margin: 0;
  color: #172033;
  font-size: 17px;
  font-weight: 800;
}
.modal-head p {
  margin: 7px 0 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.8;
}
.modal-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
  margin-bottom: 20px;
}
.mstat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-height: 73px;
  padding: 10px 5px;
  border: 1px solid;
  border-radius: 10px;
}
.mstat b {
  font-size: 20px;
  font-weight: 800;
}
.mstat span {
  font-size: 11px;
}
.mstat-green {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
}
.mstat-orange {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.mstat-gray {
  border-color: #e2e8f0;
  background: #f8fafc;
  color: #475569;
}
.modal-kosong {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}
.modal-kosong-label {
  width: 100%;
  font-size: 12px;
  font-weight: 750;
}
.modal-kosong-nomor {
  min-width: 35px;
  min-height: 33px;
  padding: 5px 9px;
  border: 1px solid #fdba74;
  border-radius: 7px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 12px;
  font-weight: 750;
}
.modal-warning {
  margin-bottom: 22px;
  padding: 13px 14px;
  border: 1px solid #fecdd3;
  border-radius: 9px;
  background: #fff1f2;
  color: #9f1239;
  font-size: 12px;
  line-height: 1.8;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 9px;
}
.mbtn {
  min-height: 41px;
  padding: 10px 13px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 750;
}
.mbtn-yellow {
  border-color: #f4c35f;
  background: #fbbf24;
  color: #713f12;
}
.mbtn-navy {
  border-color: #1d4ed8;
  background: #1d4ed8;
  color: #fff;
}
.mbtn-ghost {
  border-color: #fecaca;
  background: #fff;
  color: #b91c1c;
}
@media (max-width: 1100px) {
  .main {
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 16px;
    padding: 22px;
  }
  .soal {
    padding: 23px;
  }
  .ringkasan {
    padding: 19px;
  }
  .nomer-grid {
    gap: 6px;
  }
}
@media (max-width: 850px) {
  .main {
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    padding: 20px;
  }
  .col-right {
    position: static;
  }
  .nomer-grid {
    grid-template-columns: repeat(8, minmax(0, 1fr));
  }
  .legend {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .header {
    align-items: flex-start;
    flex-direction: column;
    gap: 13px;
    padding: 16px;
  }
  .header h1 {
    font-size: 19px;
  }
  .header-right {
    justify-content: flex-start;
    width: 100%;
    gap: 8px;
  }
  .timer-label {
    display: none;
  }
  .main {
    gap: 15px;
    padding: 13px;
  }
  .col-left {
    gap: 15px;
  }
  .card {
    border-radius: 12px;
  }
  .petunjuk {
    padding: 18px;
  }
  .tipe-grid {
    grid-template-columns: 1fr;
    gap: 9px;
  }
  .tipe {
    padding: 13px;
  }
  .soal {
    padding: 17px 14px;
  }
  .soal-meta {
    align-items: flex-start;
    flex-direction: column;
  }
  .konteks {
    padding: 13px;
  }
  .pertanyaan {
    font-size: 14px;
  }
  .opsi {
    gap: 8px;
    padding: 11px 10px;
    font-size: 13px;
  }
  .nav {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
  }
  .btn {
    width: 100%;
  }
  .ringkasan {
    padding: 17px 14px;
  }
  .nomer-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 7px;
  }
  .legend {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .footer {
    padding: 17px 15px;
  }
  .footer-inner {
    flex-direction: column;
    gap: 5px;
  }
  .modal {
    max-height: calc(100vh - 24px);
    padding: 20px 15px;
  }
  .modal-actions {
    flex-direction: column;
  }
  .mbtn {
    width: 100%;
  }
}
@media (max-width: 360px) {
  .main {
    padding: 9px;
  }
  .soal {
    padding: 14px 11px;
  }
  .ringkasan {
    padding: 14px 10px;
  }
  .nomer-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
</style>