<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $table = 'branches';

    public $timestamps = true;

    protected $primaryKey = 'branch_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'branch_name',
        'location',
        'address',
        'city',
        'province',
        'latitude',
        'longitude',
        'attendance_radius_meters',
        'timezone',
        'is_active',
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'attendance_radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'branch_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'branch_id', 'branch_id');
    }
}
