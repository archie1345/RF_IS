<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transactions extends Model
{
    use SoftDeletes;

    public const TYPE_PAYMENT = 'PAYMENT';

    public const TYPE_REFUND = 'REFUND';

    protected $table = 'payment_transactions';

    public $timestamps = true;

    protected $primaryKey = 'ptid';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'payment_id',
        'verified_by',
        'amount',
        'transaction_date',
        'payment_method',
        'transaction_type',
        'notes',
        'proof_path',
        'proof_notes',
    ];

    protected $dates = ['deleted_at', 'transaction_date'];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }
}
