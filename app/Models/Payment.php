<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';
    public $timestamps = true;
    protected $primaryKey = 'payment_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'athlete_id',
        'payment_type',
        'amount',
        'reference_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_date',
        'status',
        'notes',
    ];

    protected $dates = ['deleted_at', 'payment_date'];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }
}
