<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCoachRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'coach_id',
        'role',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }
}
