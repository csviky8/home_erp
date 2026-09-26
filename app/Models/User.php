<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'household_id', 'name', 'email', 'phone', 'avatar_path', 'password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function familyMember(): HasOne
    {
        return $this->hasOne(FamilyMember::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * The household this user is currently working in, and therefore the only one whose data
     * they can see or write. A super admin picks it in Settings; everyone else is pinned to the
     * household on their account.
     */
    public function workingHouseholdId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return (int) ($this->default_household_id
                ?: $this->household_id
                ?: Household::query()->orderBy('id')->value('id')) ?: null;
        }

        return $this->household_id ? (int) $this->household_id : null;
    }

    public function canAccessHousehold(Household|int|null $household): bool
    {
        $id = $household instanceof Household ? $household->id : $household;
        $working = $this->workingHouseholdId();

        return $working !== null && $id !== null && (int) $working === (int) $id;
    }
}
