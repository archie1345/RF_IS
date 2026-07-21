<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }

    public function billableUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billable_user_id', 'id');
    }

    public function payeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id', 'id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id', 'id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id', 'payment_id')
            ->latest('transaction_date')
            ->latest('ptid');
    }
}
