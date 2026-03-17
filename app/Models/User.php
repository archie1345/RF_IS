<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'uid';

    protected $fillable = [
        'email',
        'pass_hash',
        'role',
        'is-active'
    ];

    protected $hidden = [
        'pass_hash'
    ];

    protected $timestamps = true;

    public function getAuthPassword()
    {
        return $this->pass_hash;
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