<script setup>
import LegalPageLayout from '@/layouts/LegalPageLayout.vue'
</script>

<template>
  <LegalPageLayout
    title="Kebijakan Privasi"
    intro="Penjelasan tentang data yang digunakan SIAP OSN untuk menyediakan akun dan kegiatan belajar."
  >
    <section>
      <h2>Pengelola dan kontak</h2>
      <p>
        SIAP OSN dikelola oleh Tim Dev PTI A UM 23. Pertanyaan atau permintaan terkait data pribadi
        dapat dikirim ke <a href="mailto:siaposnptium@gmail.com">siaposnptium@gmail.com</a>.
      </p>
    </section>

    <section>
      <h2>Data yang digunakan</h2>
      <ul>
        <li>Data akun yang Anda berikan, seperti nama dan alamat email. Kata sandi disimpan dalam bentuk hash.</li>
        <li>Aktivitas belajar, termasuk jawaban, nilai, progres materi, dan riwayat latihan atau simulasi.</li>
        <li>Kode referal dan hubungan undangan jika fitur tersebut digunakan.</li>
        <li>
          Saat login Google tersedia, identitas dasar dari Google seperti ID akun, nama, email, dan
          status verifikasi email. Kami tidak meminta akses ke Gmail atau Google Drive.
        </li>
      </ul>
      <p>
        Server juga dapat mencatat informasi teknis yang diperlukan untuk menjalankan dan mengamankan
        layanan, seperti waktu akses dan alamat IP.
      </p>
    </section>

    <section>
      <h2>Tujuan penggunaan</h2>
      <p>
        Data akun dipakai untuk pendaftaran dan login. Data belajar dipakai untuk menampilkan progres,
        menyusun rekomendasi materi, serta menghitung hasil latihan, pretest, dan simulasi. Catatan
        teknis dipakai untuk menjaga layanan dan menelusuri gangguan.
      </p>
    </section>

    <section>
      <h2>Login Google dan penyimpanan sesi</h2>
      <p>
        Jika Anda memilih login Google setelah fitur ini aktif, Google memproses autentikasi sesuai
        kebijakan Google. SIAP OSN memakai identitas dasar yang diberikan untuk mengenali akun Anda.
        Token login aplikasi disimpan di browser agar Anda tetap masuk; keluar dari akun akan
        menghapus token tersebut dari browser yang digunakan.
      </p>
    </section>

    <section>
      <h2>Data anak</h2>
      <p>
        Siswa di bawah 18 tahun boleh menggunakan SIAP OSN dengan persetujuan orang tua atau wali.
        Orang tua atau wali dapat menghubungi alamat email di atas untuk menanyakan atau meminta
        penanganan data anak.
      </p>
    </section>

    <section>
      <h2>Permintaan terkait data</h2>
      <p>
        Untuk meminta akses, koreksi, atau penghapusan data akun, kirim email ke
        <a href="mailto:siaposnptium@gmail.com">siaposnptium@gmail.com</a> dari alamat yang terkait
        dengan akun. Kami akan memeriksa kepemilikan akun sebelum menindaklanjuti permintaan.
      </p>
    </section>

    <section>
      <h2>Perubahan kebijakan</h2>
      <p>Jika cara layanan menggunakan data berubah, halaman ini akan diperbarui.</p>
    </section>
  </LegalPageLayout>
</template>
