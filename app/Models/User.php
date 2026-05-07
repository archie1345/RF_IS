<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

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
        // 'is-active'
    ];

    protected $hidden = [
        'password',
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
        return $this->role === 'admin';
    }

    public function isCoach()
    {
        return $this->role === 'coach';
    }

    public function isParent()
    {
        return $this->role === 'parent';
    }

    public function isAthlete()
    {
        return $this->role === 'athlete';
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(Parents::class, 'id', 'id');
    }

    public function children(): HasManyThrough
    {
        return $this->hasManyThrough(
            Athlete::class,
            Parents::class,
            'id',
            'parent_id',
            'id',
            'parent_id',
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
}
