<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
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

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isCoach()
    {
        return $this->hasRole('coach');
    }

    public function isParent()
    {
        return $this->hasRole('parent');
    }

    public function isAthlete()
    {
        return $this->hasRole('athlete');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function assignedRoles(): array
    {
        if (! $this->relationLoaded('roleAssignments')) {
            $this->load('roleAssignments');
        }

        $roles = $this->roleAssignments->pluck('role')->filter()->unique()->values()->all();

        if (count($roles) === 0 && ! empty($this->role)) {
            return [$this->role];
        }

        return $roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->assignedRoles(), true);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(Parents::class, 'id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Athlete::class, 'parent_id', 'id');
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

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
