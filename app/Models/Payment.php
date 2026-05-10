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
        'billable_user_id',
        'payee_user_id',
        'bill_kind',
        'payer_user_id',
        'payment_type',
        'amount',
        'reference_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_date',
        'status',
        'notes',
        'proof_path',
        'proof_status',
        'proof_notes',
    ];

    protected $dates = ['deleted_at', 'payment_date'];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }

    public function billableUser()
    {
        return $this->belongsTo(User::class, 'billable_user_id', 'id');
    }

    public function payeeUser()
    {
        return $this->belongsTo(User::class, 'payee_user_id', 'id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_user_id', 'id');
    }
}
