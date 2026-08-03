<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $table = 'licenses';

    protected $primaryKey = 'license_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'license_number',
        'license_type',
        'level',
        'issued_date',
        'expiry_date',
        'issued_by',
    ];

    protected $dates = ['deleted_at', 'issued_date', 'expiry_date'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
