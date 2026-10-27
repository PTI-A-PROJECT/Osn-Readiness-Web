# Bug Fix Berdasarkan Report TestCase Playwright SIAP OSN

Dokumen ini menjelaskan perbaikan source yang dilakukan sebagai tindak lanjut dari bug report resmi pada repository GitHub.

## Sumber acuan resmi

- Report: [`TestCase/playwright-testing-siaposn/REPORT_TESTING_SIAP_OSN.md`](https://github.com/PTI-A-PROJECT/Osn-Readiness-Web/blob/monorepo/TestCase/playwright-testing-siaposn/REPORT_TESTING_SIAP_OSN.md)
- Test case: [`TestCase/playwright-testing-siaposn/tests/bug-detection.spec.ts`](https://github.com/PTI-A-PROJECT/Osn-Readiness-Web/blob/monorepo/TestCase/playwright-testing-siaposn/tests/bug-detection.spec.ts)
- Branch sumber: `monorepo`

Dokumentasi ini dibuat agar setiap perubahan source dapat dilacak langsung ke laporan bug yang dirilis oleh tim QA.

## Basis branch

Branch `fix/bug-report-testcase` dibuat dari `origin/monorepo` terbaru yang sudah memuat:

- Fitur kode referal (merge PR #35).
- Perbaikan rekomendasi materi (merge PR #36).
- Folder TestCase (merge PR #37).

Dengan dasar ini, perbaikan form juga mencakup field kode referal yang sudah tersedia pada branch `monorepo`.

## Ruang lingkup perbaikan pada branch ini

| Bug pada report | Komponen | Perubahan pada branch ini |
| --- | --- | --- |
| Atribut `name` kosong pada seluruh elemen input formulir | `RegisterForm.vue`, `LoginForm.vue` | Menambahkan atribut `name` pada setiap input |

Dua bug lain pada report sudah terselesaikan sebelumnya di source aplikasi dan tidak diubah ulang pada branch ini:

| Bug | Status |
| --- | --- |
| State initialization pada halaman simulasi saat direct URL/refresh | Sudah diperbaiki sebelumnya di `SimulasiView.vue` |
| Validasi registrasi tanpa pesan teks error | Sudah diperbaiki sebelumnya di `RegisterForm.vue` |

Satu temuan tambahan ditemukan pada test case GitHub tetapi tidak dijelaskan di report Markdown:

| Temuan | Status |
| --- | --- |
| Tombol `Kumpulkan yang Kosong` disabled pada modal ujian | Belum diubah; perlu keputusan aturan bisnis |

## Bug yang diperbaiki pada branch ini

### BUG-03 pada report — Atribut `name` kosong pada input formulir

#### Deskripsi dari report

Report menyatakan seluruh elemen formulir registrasi dan login memiliki atribut `name` kosong atau tidak ada.

Dampak yang disebutkan:

- Mengganggu autofill browser.
- Mengganggu password manager seperti Google Password Manager, Apple Keychain, dan Bitwarden.
- Menurunkan kepatuhan aksesibilitas.

#### Kondisi sebelum perbaikan

Input hanya memiliki `id`, `v-model`, dan `autocomplete`. Contoh pada `RegisterForm.vue`:

```vue
<input
  id="email"
  v-model="email"
  autocomplete="email"
/>
```

Ketika dibaca browser:

```js
input.getAttribute('name') === null
```

#### Perubahan pada `RegisterForm.vue`

| ID input | `name` yang ditambahkan |
| --- | --- |
| `nama` | `name` |
| `email` | `email` |
| `password` | `password` |
| `konfirmasi` | `password_confirmation` |
| `referal` | `kode_referal` |
| `agreement` | `agreement` |

Contoh hasil perbaikan:

```vue
<input
  id="email"
  name="email"
  v-model="email"
  autocomplete="email"
  type="email"
/>
```

Contoh untuk field kode referal yang sudah tersedia di branch `monorepo`:

```vue
<input
  id="referal"
  name="kode_referal"
  v-model="referal"
  type="text"
/>
```

#### Perubahan pada `LoginForm.vue`

| ID input | `name` yang ditambahkan |
| --- | --- |
| `email` | `email` |
| `password` | `password` |
| `remember` | `remember` |

Contoh hasil perbaikan:

```vue
<input
  id="password"
  name="password"
  v-model="password"
  autocomplete="current-password"
  type="password"
/>
```

#### Dampak teknis

Perubahan hanya menambahkan metadata semantik pada elemen form. Tidak ada perubahan pada:

- Nama state Vue.
- Payload API register.
- Payload API login.
- Validasi backend.
- Struktur database.
- Alur autentikasi.
- Tampilan visual.

Perubahan bersifat aman dan backward-compatible.

## Catatan bug lain pada report

### BUG-01 pada report — State initialization halaman simulasi

Report menyebutkan direct URL atau refresh pada `/siswa/simulasi` dapat menampilkan:

```text
Belum ada data tingkat. Coba muat ulang halaman.
```

Source terbaru sudah memuat tingkat pada `onMounted()` di `SimulasiView.vue`:

```js
onMounted(async () => {
  await progress.fetchDashboard().catch(() => {})
  await pretest.muatTingkat({ force: true })
  // memilih tingkat terbuka yang tersedia
})
```

Status: sudah diperbaiki sebelum branch ini dibuat.

### BUG-02 pada report — Pesan error validasi register

Report menyebutkan password invalid tidak menampilkan pesan teks.

Source terbaru sudah menampilkan pesan:

```vue
<small v-if="passwordError" id="password-error">
  {{ passwordError }}
</small>
```

Contoh pesan:

```text
Password minimal 8 karakter
```

Status: sudah diperbaiki sebelum branch ini dibuat.

## Temuan tambahan dari test case GitHub

Test case `bug-detection.spec.ts` memiliki test tambahan yang tidak tercatat pada report Markdown:

### Tombol `Kumpulkan yang Kosong` disabled

Test menguji bahwa modal ujian menawarkan opsi `Kumpulkan yang Kosong`, tetapi tombol tersebut disabled.

Source saat ini:

```vue
<button
  v-if="ringkasan.belum"
  :disabled="!bolehKumpulkanKosong || mengirim"
  @click="kirim(true)"
>
  Kumpulkan yang Kosong
</button>
```

Temuan ini belum diubah karena membutuhkan keputusan aturan bisnis:

1. Jika soal kosong boleh dikumpulkan, parent harus mengirim `bolehKumpulkanKosong=true`.
2. Jika soal kosong tidak boleh dikumpulkan, teks modal harus diubah agar tidak menawarkan opsi yang disabled.

Rencana tindak lanjut:

- Tetapkan aturan untuk pretest, latihan, dan simulasi.
- Tambahkan test untuk kondisi tombol aktif.
- Tambahkan test untuk kondisi tombol disabled.
- Selaraskan teks modal dengan aturan bisnis.

## Validasi

Perintah yang relevan untuk frontend:

```bash
cd apps/siap-osn-fe
bun run test:unit
bun run build
```

Hasil pada source terbaru:

```text
Unit test frontend: 200 passed
Build frontend: berhasil
```

Verifikasi browser untuk atribut `name`:

```text
register inputs: semua memiliki name
login inputs: semua memiliki name
```

## File yang berubah pada branch ini

```text
apps/siap-osn-fe/src/components/auth/RegisterForm.vue
apps/siap-osn-fe/src/components/auth/LoginForm.vue
docs/BUG-FIX-BERDASARKAN-REPORT-TESTCASE.md
```

## Referensi commit

Branch:

```text
fix/bug-report-testcase
```

Dasar branch:

```text
origin/monorepo
```
