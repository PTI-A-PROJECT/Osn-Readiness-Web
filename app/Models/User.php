<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

#[Fillable(['name', 'email', 'password', 'is_active', 'tingkat_aktif_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'users';

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
            'is_active' => 'boolean',
        ];
    }

    public function tingkatAktif(): BelongsTo
    {
        return $this->belongsTo(TingkatSeleksi::class, 'tingkat_aktif_id');
    }

    public function pretest(): HasMany
    {
        return $this->hasMany(Pretest::class, 'user_id');
    }

    public function progressBelajar(): HasMany
    {
        return $this->hasMany(ProgressBelajar::class, 'user_id');
    }

    public function quizPengerjaan(): HasMany
    {
        return $this->hasMany(QuizPengerjaan::class, 'user_id');
    }

    public function hasilSimulasi(): HasMany
    {
        return $this->hasMany(HasilSimulasi::class, 'user_id');
    }

    public function kenaikanTingkat(): HasMany
    {
        return $this->hasMany(KenaikanTingkat::class, 'user_id');
    }
}
