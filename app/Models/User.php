<?php

namespace App\Models;

use App\Support\RoleResolver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    public const ACCOUNT_STATUS_ACTIVE = 'active';

    public const ACCOUNT_STATUS_INVITED = 'invited';

    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'gender',
        'role',
        'bday',
        'phone',
        'account_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'bday' => 'date',
            'password' => 'hashed',
        ];
    }

    public function isActiveAccount(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_ACTIVE;
    }

    public function isInvited(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_INVITED;
    }

    public function isSuspended(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_SUSPENDED;
    }

    /*
     * Role checks evaluate the union of every role assigned to the account.
     * There is no session-scoped "active role": the same account has the same
     * permissions on every request. Code that narrows access for a lower role
     * (e.g. "coach sees only own classes") must therefore check isAdmin() first.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isCoach(): bool
    {
        return $this->hasRole('coach');
    }

    public function isAthlete(): bool
    {
        return $this->hasRole('athlete');
    }

    public function assignedRoles(): array
    {
        return app(RoleResolver::class)->rolesFor($this);
    }

    public function primaryRole(string $default = 'athlete'): string
    {
        return app(RoleResolver::class)->primaryRoleFor($this, $default);
    }

    public function hasRole(string $role): bool
    {
        return app(RoleResolver::class)->hasRole($this, $role);
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        $normalizedRole = strtolower(trim($role));

        if (! in_array($normalizedRole, RoleResolver::ROLES, true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $roleQuery) use ($normalizedRole): void {
            $roleQuery
                ->whereHas(
                    'roleAssignments',
                    fn (Builder $assignmentQuery): Builder => $assignmentQuery->where('role', $normalizedRole),
                )
                ->orWhere(function (Builder $legacyQuery) use ($normalizedRole): void {
                    $legacyQuery
                        ->whereDoesntHave('roleAssignments')
                        ->whereRaw('LOWER(role) = ?', [$normalizedRole]);
                });
        });
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function athleteProfile(): HasOne
    {
        return $this->hasOne(Athlete::class, 'id', 'id');
    }

    public function coachProfile(): HasOne
    {
        return $this->hasOne(Coach::class, 'id', 'id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'id');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(UserCertification::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function displayNik(): string
    {
        return $this->profile?->displayNik() ?? '';
    }

    public function displayBpjs(): string
    {
        return $this->profile?->displayBpjs() ?? '';
    }
}
