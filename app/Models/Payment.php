<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Payment extends Model
{
    use SoftDeletes;

    public const PROOF_DISK_PRIVATE = 'local';

    public const PROOF_DISK_PUBLIC = 'public';

    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'invoice_number',
        'athlete_id',
        'billable_user_id',
        'payee_user_id',
        'bill_kind',
        'payer_user_id',
        'payment_type',
        'amount',
        'reference_id',
        'billing_rule_id',
        'billing_run_key',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_date',
        'due_date',
        'collection_method',
        'status',
        'notes',
        'proof_path',
        'proof_disk',
        'proof_status',
        'proof_notes',
    ];

    protected $hidden = [
        'proof_path',
        'billing_run_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $issuedOn = Carbon::parse($payment->payment_date ?? today());
            $payment->payment_date ??= $issuedOn->toDateString();
            $payment->due_date ??= strtoupper((string) ($payment->bill_kind ?? 'INVOICE')) === 'PAYROLL'
                ? $issuedOn->toDateString()
                : $issuedOn->copy()->addDays(14)->toDateString();
            $payment->collection_method ??= 'TRANSFER';
        });

        static::created(function (Payment $payment): void {
            if (blank($payment->invoice_number)) {
                $issuedOn = Carbon::parse($payment->payment_date ?? $payment->created_at ?? today());
                $payment->forceFill([
                    'invoice_number' => 'INV-'.$issuedOn->format('Ym').'-'.str_pad((string) $payment->payment_id, 6, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'payment_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function proofStorageDisk(): string
    {
        return $this->proof_disk ?: self::PROOF_DISK_PUBLIC;
    }

    public function isOverdue(): bool
    {
        return $this->status === 'PENDING'
            && (float) ($this->remaining_amount ?? 0) > 0
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
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

    public function billingRule(): BelongsTo
    {
        return $this->belongsTo(BillingRule::class, 'billing_rule_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id', 'payment_id')
            ->latest('transaction_date')
            ->latest('ptid');
    }
}
