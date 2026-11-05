<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import { useAdminStore } from '@/stores/admin.js'
import { adminService } from '@/services/admin.js'
import { useAuthStore } from '@/stores/auth.js'
import { pesanError } from '@/lib/errors.js'

// Dashboard admin (Fase 6 A1). Semua angka dari GET /admin/dashboard;
// laporan kecukupan bank soal diambil terpisah karena butuh tingkat_id.

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const admin = useAdminStore()

const tingkatId = ref(null)
const tingkatList = ref([])
const galatKecukupan = ref('')
const galatRingkasan = ref('')

const r = computed(() => admin.ringkasan)
const k = computed(() => admin.kecukupan)
const aktivitasTotal = computed(() => (r.value?.pengerjaanPerJenis ?? []).reduce((total, x) => total + x.jumlah, 0))
const simulasiNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'simulasi')?.nilai ?? null)
const pretestNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'pretest')?.nilai ?? null)
const latihanNilai = computed(() => r.value?.rataRataNilai.find((item) => item.jenis === 'latihan')?.nilai ?? null)
const jumlahSiswaTingkat = computed(() => (r.value?.siswaPerTingkat ?? []).reduce((total, item) => total + item.jumlahSiswa, 0))
const kurangBank = computed(() => admin.bankSoalRingkas?.kurang ?? null)
const materiButuhPerhatian = computed(() => (k.value?.latihanPerMateri ?? []).filter((x) => x.kurang || !x.punyaLatihan).slice(0, 4))

async function muatSemuanya() {
  galatRingkasan.value = ''
  galatKecukupan.value = ''
  try {
    await admin.muatRingkasan()
  } catch (err) {
    galatRingkasan.value = pesanError(err, 'Gagal memuat ringkasan admin.')
  }
  try {
    tingkatList.value = await adminService.daftarTingkatAdmin()
    if (!tingkatId.value && tingkatList.value.length) tingkatId.value = tingkatList.value[0].id
    if (tingkatId.value) await admin.muatKecukupan({ tingkatId: tingkatId.value, force: true })
  } catch (err) {
    galatKecukupan.value = pesanError(err, 'Gagal memuat laporan bank soal.')
  }
}

function gantiTingkat(id) {
  tingkatId.value = Number(id)
  admin
    .muatKecukupan({ tingkatId: tingkatId.value, force: true })
    .catch((err) => {
      galatKecukupan.value = pesanError(err, 'Gagal memuat laporan bank soal.')
    })
}

// Bukti role-guard backend bekerja dari browser: hanya super_admin yang
// bisa lolos RequireRole di GET /admin/dashboard.
async function tesAksesAdmin() {
  try {
    await adminService.dashboard()
    toast.add({ severity: 'success', summary: 'Akses admin OK', life: 4000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Akses admin ditolak', detail: pesanError(err), life: 4000 })
  }
}

async function keluar() {
  await auth.logout()
  router.push({ name: 'login' })
}

function formatNilai(n) {
  return n == null ? '—' : String(Math.round(n))
}

onMounted(muatSemuanya)
</script>

<template>
  <div>
    <AdminPageHeader
      judul="Dashboard Super Admin"
      deskripsi="Ringkasan sistem, aktivitas siswa, dan status data SIAP OSN"
      :galat="galatRingkasan"
      @coba-lagi="muatSemuanya"
    >
      <template #aksi-extra>
        <Button label="Tes akses" size="small" severity="secondary" outlined :disabled="admin.loading" @click="tesAksesAdmin" />
        <Button label="Keluar" size="small" severity="secondary" outlined @click="keluar" />
      </template>
    </AdminPageHeader>

    <div v-if="admin.loading && !r" class="grid gap-4 md:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-[136px] animate-pulse rounded-2xl border border-[#e0e7f2] bg-white p-5">
        <div class="mb-5 h-8 w-8 rounded-lg bg-[#edf2fa]"></div>
        <div class="h-5 w-2/3 rounded bg-[#edf2fa]"></div>
        <div class="mt-2 h-3 w-1/2 rounded bg-[#edf2fa]"></div>
      </div>
    </div>

    <template v-else-if="r">
      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#e7efff]"></div>
          <p class="text-[25px] font-bold leading-none">{{ r.siswaAktif }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Total Siswa Aktif</p>
          <p class="mt-1 text-[11px] font-medium text-[#159564]">Dari data akun aktif</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#e0f5e8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ aktivitasTotal }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Total Pengerjaan Tercatat</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Akumulasi seluruh jenis asesmen</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#fff3d8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ simulasiNilai == null ? '—' : formatNilai(simulasiNilai) }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Rata-rata Skor Simulasi</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Berdasarkan hasil yang tersedia</p>
        </article>
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="mb-4 h-8 w-8 rounded-xl bg-[#edf1f8]"></div>
          <p class="text-[25px] font-bold leading-none">{{ kurangBank == null ? '—' : kurangBank }}</p>
          <p class="mt-2 text-[12px] text-[#66748c]">Bagian Bank Soal Perlu Dilengkapi</p>
          <p class="mt-1 text-[11px] text-[#64748b]">Pada tingkat yang dipilih</p>
        </article>
      </section>

      <section class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <h2 class="mb-4 text-[14px] font-bold">Distribusi Siswa per Tingkat</h2>
          <ul v-if="r.siswaPerTingkat.length" class="space-y-4">
            <li v-for="(t, index) in r.siswaPerTingkat" :key="t.tingkatId" class="grid grid-cols-[100px_minmax(0,1fr)_42px] items-center gap-3 text-[12px]">
              <span class="font-semibold">{{ t.nama }}</span>
              <span class="h-2 overflow-hidden rounded-full bg-[#edf2fa]">
                <span
                  class="block h-full rounded-full"
                  :class="index === 0 ? 'bg-[#548bf1]' : 'bg-[#f3b72d]'"
                  :style="{ width: (jumlahSiswaTingkat ? t.jumlahSiswa / jumlahSiswaTingkat * 100 : 0) + '%' }"
                ></span>
              </span>
              <b class="text-right text-[#586985]">{{ t.jumlahSiswa }}</b>
            </li>
          </ul>
          <p v-else class="py-4 text-[12px] text-[#71809a]">Belum ada data distribusi tingkat.</p>

          <h2 class="mb-3 mt-8 text-[14px] font-bold">Data Hasil Pembelajaran & Simulasi</h2>
          <dl class="divide-y divide-dashed divide-[#e1e8f2] text-[12px]">
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata skor pre-test</dt><dd class="font-bold">{{ pretestNilai == null ? '—' : formatNilai(pretestNilai) + '%' }}</dd></div>
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata nilai latihan soal</dt><dd class="font-bold">{{ formatNilai(latihanNilai) }}</dd></div>
            <div class="flex justify-between gap-3 py-3"><dt class="text-[#65748d]">Rata-rata nilai simulasi</dt><dd class="font-bold">{{ formatNilai(simulasiNilai) }}</dd></div>
          </dl>
        </article>

        <article class="rounded-2xl border border-[#e0e7f2] bg-white p-5">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-[14px] font-bold">Materi Perlu Diperhatikan</h2>
            <label class="sr-only" for="tingkat-bank-admin">Pilih tingkat</label>
            <select id="tingkat-bank-admin" class="max-w-[155px] rounded-lg border border-[#e0e7f2] bg-white px-2 py-1.5 text-[11px]"
              :value="tingkatId ?? ''" @change="gantiTingkat($event.target.value)">
              <option v-for="t in tingkatList" :key="t.id" :value="t.id">{{ t.nama }}</option>
            </select>
          </div>

          <p v-if="galatKecukupan" class="mt-4 text-[12px] text-[#a33333]">{{ galatKecukupan }}</p>
          <ul v-else-if="materiButuhPerhatian.length" class="mt-4 divide-y divide-dashed divide-[#e1e8f2]">
            <li v-for="item in materiButuhPerhatian" :key="item.materiId" class="flex items-center justify-between gap-3 py-3 text-[12px]">
              <div class="min-w-0"><p class="truncate font-bold">{{ item.judul }}</p><p class="mt-1 text-[11px] text-[#71809a]">{{ item.punyaLatihan ? 'Jumlah soal belum mencukupi' : 'Belum memiliki latihan' }}</p></div>
              <button type="button" class="shrink-0 rounded-lg bg-[#f2b82b] px-3 py-1.5 text-[11px] font-bold text-[#18263a]" @click="router.push({ name: 'admin.ujian', query: { tab: 'latihan' } })">Periksa</button>
            </li>
          </ul>
          <div v-else class="py-9 text-center text-[12px] text-[#71809a]">{{ k ? 'Tidak ada kekurangan latihan pada tingkat ini.' : 'Pilih tingkat untuk memeriksa materi.' }}</div>

          <h2 class="mt-7 text-[14px] font-bold">Notifikasi Sistem</h2>
          <div class="mt-3 flex min-h-[120px] flex-col items-center justify-center text-center">
            <strong class="text-[13px]">Belum ada notifikasi baru</strong>
            <p class="mt-1 max-w-[300px] text-[11px] leading-relaxed text-[#71809a]">Notifikasi otomatis belum disediakan oleh endpoint Dashboard Admin.</p>
          </div>
        </article>
      </section>

      <section class="mt-5 rounded-2xl border border-[#e0e7f2] bg-white p-5">
        <h2 class="text-[14px] font-bold">Aktivitas Sistem & Kecukupan Bank Soal</h2>
        <p class="mt-1 text-[11px] text-[#71809a]">Ringkasan kebutuhan bank soal menurut tingkat yang sedang dipilih.</p>
        <div v-if="k" class="mt-4 grid gap-4 md:grid-cols-3">
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Pre-Test per Level</h3>
            <p v-for="x in k.pretestPerLevel" :key="x.level" class="flex justify-between gap-2 py-1.5 text-[11px]"><span>{{ x.label }}</span><span :class="x.kurang ? 'font-bold text-[#d18a00]' : 'text-[#159564]'">{{ x.tersedia }} / {{ x.kuota }}</span></p>
          </div>
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Simulasi per Level</h3>
            <div v-for="s in k.simulasiPerLevel" :key="s.simulasiId" class="mb-2">
              <p class="text-[11px] font-semibold">{{ s.nama }}</p>
              <p v-for="x in s.perLevel" :key="x.level" class="flex justify-between gap-2 py-1 text-[11px] text-[#64748b]"><span>{{ x.label }}</span><span>{{ x.tersedia }} / {{ x.kuota }}</span></p>
            </div>
          </div>
          <div class="rounded-xl border border-[#e9edf5] p-4">
            <h3 class="mb-2 text-[12px] font-bold">Putaran Pre-Test</h3>
            <p class="text-[12px] text-[#64748b]">{{ k.putaranPretest.putaran }} putaran · ambang {{ k.putaranPretest.ambang }}</p>
            <button type="button" class="mt-4 rounded-lg border border-[#dfe6f2] px-3 py-2 text-[11px] font-semibold text-[#173e79]" @click="router.push({ name: 'admin.soal', query: { tab: 'soal' } })">Lihat Bank Soal →</button>
          </div>
        </div>
        <p v-else class="py-6 text-center text-[12px] text-[#71809a]">Data kecukupan bank soal belum tersedia.</p>
      </section>
    </template>
  </div>
</template>