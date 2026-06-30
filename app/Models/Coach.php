<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Coach extends Model
{
    use SoftDeletes, HasUlids;

    protected $table = 'coaches';
    public $timestamps = true;
    protected $primaryKey = 'coach_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'status',
        'specialization',
        'bio',
    ];

    protected $dates = ['deleted_at'];

    public function getRouteKeyName(): string
    {
        return 'coach_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
