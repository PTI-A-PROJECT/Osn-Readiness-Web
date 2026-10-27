<?php

namespace App\Support;

use App\Models\User;
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
 * Dipakai model User (pengisian otomatis saat akun dibuat) dan
 * ApiRegisterRequest (perapian input kode pengundang).
 */
final class KodeReferal
{
    public const SET_KARAKTER = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const PANJANG = 4;

    /** Batas percobaan mencari kode yang belum terpakai sebelum menyerah. */
    public const MAKS_PERCOBAAN = 10;

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
}
