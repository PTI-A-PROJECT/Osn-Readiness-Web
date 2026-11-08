<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kode referal 4 karakter campuran huruf dan angka, mis. "K7XW".
 *
 * Huruf dan angka yang mudah tertukar saat dibaca atau dibacakan (0/O/1/I)
 * dibuang dari set; sisanya 32 karakter sehingga ruang kodenya 32^4 sekitar
 * satu juta kombinasi — tabrakan jarang dan tetap ditangani retry di
 * buatUnik(). Kolom database memakai string agar kode selalu dibandingkan
 * sebagai teks, bukan angka.
 *
 * Kode anggota berbentuk OSN<NAMA>, mis. "OSNACHMAD", dan dibuat lewat
 * untukAnggota(). Huruf O tidak ada di SET_KARAKTER, jadi kode acak siswa tidak
 * pernah bertabrakan dengan awalan OSN.
 *
 * Dipakai model User (pengisian otomatis saat akun dibuat) dan
 * ApiRegisterRequest (perapian input kode pengundang).
 */
final class KodeReferal
{
    public const SET_KARAKTER = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const PANJANG = 4;

    /** Batas percobaan mencari kode yang belum terpakai sebelum menyerah. */
    public const MAKS_PERCOBAAN = 10;

    public const PREFIX_ANGGOTA = 'OSN';

    /** Panjang maksimal satu kata nama pada kode anggota. */
    public const NAMA_MAKS = 16;

    /** Nomor urut tertinggi penyambung bila semua kata nama sudah terpakai. */
    private const URUT_MAKS = 99;

    /**
     * Kode yang dikirim user dirapikan: spasi tepi dibuang dan hurufnya
     * dibesarkan, sehingga " k7xw " tetap cocok dengan kode tersimpan "K7XW".
     */
    public static function normalisasi(?string $kode): ?string
    {
        $kode = trim((string) $kode);

        return $kode === '' ? null : strtoupper($kode);
    }

    public static function buat(): string
    {
        $set = self::SET_KARAKTER;
        $maks = strlen($set) - 1;
        $kode = '';

        for ($i = 0; $i < self::PANJANG; $i++) {
            $kode .= $set[random_int(0, $maks)];
        }

        return $kode;
    }

    /**
     * Kode acak yang belum dipakai akun mana pun. Cek database tiap percobaan;
     * index unik di kolom kode_referal tetap menjadi pengaman terakhir.
     */
    public static function buatUnik(): string
    {
        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN; $percobaan++) {
            $kode = self::buat();

            if (! User::query()->where('kode_referal', $kode)->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode referal unik.');
    }

    /** Kode anggota dari bagian nama: "budi" -> "OSNBUDI". */
    public static function kodeAnggota(string $nama): string
    {
        return self::PREFIX_ANGGOTA.strtoupper($nama);
    }

    /**
     * Kata-kata nama dibersihkan menjadi A-Z0-9, dipotong NAMA_MAKS, kata kosong dibuang.
     *
     * @return list<string>
     */
    public static function kataNama(string $nama): array
    {
        $kata = [];

        foreach (preg_split('/\s+/', trim($nama)) ?: [] as $k) {
            $bersih = substr((string) preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($k))), 0, self::NAMA_MAKS);

            if ($bersih !== '') {
                $kata[] = $bersih;
            }
        }

        return $kata;
    }

    /**
     * OSN<NAMA>: kata pertama dari nama lengkap yang belum menjadi kode siapa
     * pun. Bila semua kata sudah terpakai, kata pertama disambung nomor urut
     * terkecil yang masih bebas (OSNBUDI2, OSNBUDI3, ...). Tanpa kata sama
     * sekali dipakai "ANGGOTA". Akun terhapus ikut dihitung karena index unik
     * kode_referal juga memegang barisnya.
     */
    public static function untukAnggota(string $namaLengkap): string
    {
        $kata = self::kataNama($namaLengkap) ?: ['ANGGOTA'];

        foreach ($kata as $k) {
            $kode = self::kodeAnggota($k);

            if (! self::terpakai($kode)) {
                return $kode;
            }
        }

        for ($urut = 2; $urut <= self::URUT_MAKS; $urut++) {
            $kode = self::kodeAnggota($kata[0].$urut);

            if (! self::terpakai($kode)) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode referal unik.');
    }

    private static function terpakai(string $kode): bool
    {
        return User::withTrashed()->where('kode_referal', $kode)->exists();
    }
}
