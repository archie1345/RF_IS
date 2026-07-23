<?php

namespace App\Models;

use App\Services\ActiveRoleContextService;
use App\Support\RoleResolver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    public const ACCOUNT_STATUS_ACTIVE = 'active';

    public const ACCOUNT_STATUS_INVITED = 'invited';

    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $primaryKey = 'id';

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

    public $timestamps = true;

    protected $dates = ['deleted_at', 'bday'];

    public function getAuthPassword()
    {
        return $this->password;
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

    public function isAdmin(): bool
    {
        return $this->isActingAs('admin');
    }

    public function isCoach(): bool
    {
        return $this->isActingAs('coach');
    }

    public function isParent(): bool
    {
        return $this->isActingAs('parent');
    }

    public function isAthlete(): bool
    {
        return $this->isActingAs('athlete');
    }

    public function isActingAs(string $role): bool
    {
        if (! $this->hasRole($role)) {
            return false;
        }

        if (! $this->canResolveActiveRoleFromRequest()) {
            return true;
        }

        return app(ActiveRoleContextService::class)->activeRole(request(), $this) === strtolower(trim($role));
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
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

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class, 'id', 'id');
    }

    public function children(): HasManyThrough
    {
        return $this->hasManyThrough(
            Athlete::class,
            ParentProfile::class,
            'id',
            'parent_id',
            'id',
            'parent_id'
        );
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
        return $this->hasOne(UserProfile::class);
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

    private function canResolveActiveRoleFromRequest(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        $request = request();

        return $request->hasSession() && (int) $request->user()?->id === (int) $this->id;
    }
}
