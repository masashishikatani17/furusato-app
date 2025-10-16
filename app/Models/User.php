<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'birth_date',
        'company_id',
        'role',
        'is_active',
        'group_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'display_role',
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
            'is_active' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    /**
     * Get the company that the user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Determine if the user is an owner.
     */
    public function isOwner(): bool
    {
        $companyOwnerId = optional($this->company)->owner_user_id;

        if ($companyOwnerId !== null) {
            return (int) $companyOwnerId === (int) $this->id;
        }

        return $this->getNormalizedRole() === 'owner';
    }

    public function isRegistrar(): bool
    {
        return $this->getNormalizedRole() === 'registrar';
    }

    public function isGroupAdmin(): bool
    {
        return $this->getNormalizedRole() === 'group_admin';
    }

    public function getDisplayRoleAttribute(): string
    {
        if ($this->isOwner()) {
            return 'owner';
        }

        return $this->getNormalizedRole();
    }

    protected function getNormalizedRole(): string
    {
        $role = strtolower((string) ($this->role ?? ''));

        return match ($role) {
            'owner' => 'owner',
            'registrar' => 'registrar',
            'group_admin', 'groupadmin', 'group-admin' => 'group_admin',
            'client' => 'client',
            default => 'member',
        };
    }
}
