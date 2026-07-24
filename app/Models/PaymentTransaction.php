<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use SoftDeletes;

    public const TYPE_PAYMENT = 'PAYMENT';

    public const TYPE_PROOF_SUBMITTED = 'PROOF_SUBMITTED';

    public const TYPE_PROOF_REJECTED = 'PROOF_REJECTED';

    public const TYPE_STATUS_CHANGE = 'STATUS_CHANGE';

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

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }
}
