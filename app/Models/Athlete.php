<?php

namespace App\Models;

use App\Services\MemberNumberGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Athlete extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'athletes';

    protected $primaryKey = 'athlete_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'joined_at',
        'height_cm',
        'weight_kg',
        'school_origin',
        'geup',
        'id',
        'group_id',
        'training_group_id',
        'parent_id',
        'branch_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Athlete $athlete): void {
            $athlete->joined_at ??= today();

            if (blank($athlete->member_number)) {
                $athlete->member_number = app(MemberNumberGenerator::class)->generate($athlete->joined_at);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function privateGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'class_group_private_athletes', 'athlete_id', 'group_id', 'athlete_id', 'group_id')
            ->withTimestamps();
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'athlete_id', 'athlete_id');
    }
}