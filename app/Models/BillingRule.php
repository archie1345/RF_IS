<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class BillingRule extends Model
{
    use SoftDeletes;

    public const KIND_MONTHLY = 'MONTHLY';

    public const KIND_ONE_TIME = 'ONE_TIME';

    public const PAYMENT_TYPES = ['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER'];

    protected $fillable = [
        'name',
        'charge_kind',
        'payment_type',
        'amount',
        'branch_id',
        'group_id',
        'due_days',
        'effective_from',
        'effective_until',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'branch_id' => 'integer',
            'group_id' => 'integer',
            'due_days' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $from) use ($date): void {
                $from->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function (Builder $until) use ($date): void {
                $until->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
            });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'billing_rule_id');
    }

    public function scopeLabel(): string
    {
        return match (true) {
            $this->branch_id !== null && $this->group_id !== null => trim(($this->branch?->branch_name ?? 'Branch').' / '.($this->group?->group_name ?? 'Group')),
            $this->group_id !== null => $this->group?->group_name ?? 'Specific group',
            $this->branch_id !== null => $this->branch?->branch_name ?? 'Specific branch',
            default => 'All active athletes',
        };
    }
}
