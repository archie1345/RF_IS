<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'email',
        'password',
        'role',
        // 'is-active'
    ];

    protected $hidden = [
        'password',
    ];

    public $timestamps = true;
    protected $dates = ['deleted_at'];

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
}