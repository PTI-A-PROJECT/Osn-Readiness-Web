<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PengerjaanSoal from '@/components/soal/PengerjaanSoal.vue'
import { api } from '@/lib/api.js'
import { mapSoal } from '@/services/mappers/pretest.js'
import { pesanError } from '@/lib/errors.js'

const route = useRoute()
const router = useRouter()
const memuat = ref(true)
const galat = ref('')
const soal = ref([])
const id = Number(route.params.soalId)

onMounted(async () => {
  if (!Number.isInteger(id) || id < 1) {
    galat.value = 'ID soal tidak valid.'
    memuat.value = false
    return
  }
  try {
    // GET saja: tidak membuat paket, jawaban, atau riwayat.
    const response = await api.get(`/dev/soal/${id}`)
    const data = response.data?.data
    if (!data) throw new Error('Respons soal kosong.')
    soal.value = [mapSoal(data)]
  } catch (error) {
    galat.value = pesanError(error, 'Soal tidak dapat dimuat. Pastikan backend menggunakan APP_ENV=local dan kamu sudah login.')
  } finally {
    memuat.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#f5f8fd]">
    <div class="mx-auto max-w-5xl px-5 pt-5">
      <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Preview development — soal ID {{ id }}</strong>
        <p class="mt-1">Data langsung dari Laravel. Pilihan tidak disimpan dan tidak ada penilaian.</p>
        <button type="button" class="mt-2 underline" @click="router.push({ name: 'siswa.materi' })">
          Kembali ke Materi
        </button>
      </div>
      <p v-if="memuat" class="mt-6">Memuat soal dari database…</p>
      <p v-else-if="galat" role="alert" class="mt-6 text-red-700">{{ galat }}</p>
    </div>
    <PengerjaanSoal
      v-if="!memuat && !galat && soal.length"
      judul="Preview Soal (Development)"
      subjudul="Hanya untuk memeriksa tampilan — tidak membuat sesi latihan"
      :soal="soal"
      :total="1"
      :aktif="0"
      :jawaban="{}"
      :ragu="{}"
      :tampilkan-timer="false"
      :tombol-selesai="false"
      :boleh-kumpulkan-kosong="false"
      label-tipe="Preview"
    />
  </div>
</template>
