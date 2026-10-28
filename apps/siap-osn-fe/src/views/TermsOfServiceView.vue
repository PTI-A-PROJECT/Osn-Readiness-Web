<script setup>
import LegalPageLayout from '@/layouts/LegalPageLayout.vue'
</script>

<template>
  <LegalPageLayout
    title="Ketentuan Layanan"
    intro="Aturan dasar untuk menggunakan SIAP OSN sebagai sarana belajar dan persiapan OSN Informatika."
  >
    <section>
      <h2>Tentang layanan</h2>
      <p>
        SIAP OSN dikelola oleh Tim Dev PTI A UM 23 untuk membantu siswa belajar melalui materi,
        latihan, pretest, dan simulasi. Hasil dalam aplikasi adalah bahan belajar dan tidak menjamin
        hasil pada seleksi OSN yang sebenarnya.
      </p>
    </section>

    <section>
      <h2>Akun dan persetujuan</h2>
      <p>
        Gunakan informasi akun yang benar dan jaga akses ke akun Anda. Siswa di bawah 18 tahun boleh
        menggunakan layanan setelah mendapat persetujuan orang tua atau wali. Orang tua atau wali
        dapat menghubungi <a href="mailto:siaposnptium@gmail.com">siaposnptium@gmail.com</a>
        untuk pertanyaan mengenai penggunaan layanan oleh anak.
      </p>
    </section>

    <section>
      <h2>Penggunaan yang diizinkan</h2>
      <p>
        Gunakan materi dan soal untuk belajar. Jangan mengakses akun orang lain, mengganggu sistem,
        atau mencoba mengambil data yang bukan milik Anda.
      </p>
    </section>

    <section>
      <h2>Perubahan dan gangguan layanan</h2>
      <p>
        Materi, fitur, dan ketersediaan layanan dapat berubah. Jika terjadi gangguan, pengelola akan
        berupaya memulihkan layanan. Pertanyaan dan laporan gangguan dapat dikirim ke
        <a href="mailto:siaposnptium@gmail.com">siaposnptium@gmail.com</a>.
      </p>
    </section>

    <section>
      <h2>Data pribadi</h2>
      <p>
        Cara SIAP OSN menggunakan data dijelaskan dalam
        <RouterLink to="/kebijakan-privasi">Kebijakan Privasi</RouterLink>.
      </p>
    </section>
  </LegalPageLayout>
</template>
