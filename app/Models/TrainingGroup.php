<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingGroup extends Model
{
    protected $table = 'training_groups';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(Group::class, 'training_group_id', 'id');
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'training_group_id', 'id');
    }
}
