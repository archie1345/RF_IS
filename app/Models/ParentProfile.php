<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ParentProfile extends Model
{
    use SoftDeletes, HasUlids;

    protected $table = 'parents';
    public $timestamps = true;
    protected $primaryKey = 'parent_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'relation',
        'occupation',
        'notes',
    ];

    protected $dates = ['deleted_at'];

    public function getRouteKeyName(): string
    {
        return 'parent_id';
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class,'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
