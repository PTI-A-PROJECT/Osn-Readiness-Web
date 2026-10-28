<?php

namespace App\Models;

use App\Support\KodeReferal;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'tingkat_aktif_id', 'kode_referal', 'direferensikan_oleh', 'referal_diisi_at', 'google_sub'])]
#[Hidden(['password', 'remember_token', 'direferensikan_oleh'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        // Setiap akun baru langsung punya kode referal sendiri tanpa perlu
        // diisi pendaftar. Kode milik pengundang tidak ikut tersalin karena
        // kode pendaftar selalu dibuat oleh generator, bukan dari input.
        static::creating(function (User $user): void {
            if (empty($user->kode_referal)) {
                $user->kode_referal = KodeReferal::buatUnik();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'referal_diisi_at' => 'datetime',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TingkatSeleksi, $this>
     */
    public function tingkatAktif(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_aktif_id');
    }

    /**
     * Pemilik kode referal yang diisi saat akun ini mendaftar.
     *
     * @return BelongsTo<User, $this>
     */
    public function pemberiReferal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direferensikan_oleh');
    }

    /**
     * Akun-akun yang mendaftar memakai kode referal akun ini. Akun yang
     * sudah dihapus tidak ikut dihitung (scope SoftDeletes bawaan).
     *
     * @return HasMany<User, $this>
     */
    public function diundang(): HasMany
    {
        return $this->hasMany(User::class, 'direferensikan_oleh');
    }

    /**
     * @return HasMany<Pretest, $this>
     */
    public function pretest(): HasMany
    {
        return $this->hasMany(Pretest::class);
    }

    /**
     * @return HasMany<KenaikanTingkat, $this>
     */
    public function kenaikanTingkat(): HasMany
    {
        return $this->hasMany(KenaikanTingkat::class);
    }

    /**
     * @return HasMany<ProgressBelajar, $this>
     */
    public function progressBelajar(): HasMany
    {
        return $this->hasMany(ProgressBelajar::class);
    }
}
