<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telepon',
        'branch_id',
        'image_url',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    // Implementasi metode canAccessPanel() dari FilamentUser contract
    public function canAccessPanel(Panel $panel): bool
    {
        // Mengizinkan semua pengguna untuk mengakses panel
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->image_url
            ? asset('storage/' . $this->image_url) // Gambar avatar pengguna
            : asset('default/images/default_images.jpg'); // Gambar default jika avatar tidak ada
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Set email_verified_at to current datetime if it's not already set.
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
        });
    }
}
