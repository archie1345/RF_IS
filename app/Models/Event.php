<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'e_name',
        'e_date',
        'registration_deadline',
        'location',
        'gmaps_url',
        'level',
        'entry_fee',
        'max_slots',
        'description',
        'organizer',
        'contact_info',
        'sponsors',
        'status',
        'poster_url',
    ];

    protected function casts(): array
    {
        return [
            'e_date' => 'date',
            'registration_deadline' => 'datetime',
            'entry_fee' => 'decimal:2',
            'max_slots' => 'integer',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id', 'event_id');
    }

    public function coachRegistrations(): HasMany
    {
        return $this->hasMany(EventCoachRegistration::class, 'event_id', 'event_id');
    }
}
