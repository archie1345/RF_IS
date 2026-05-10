<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventRegistration extends Model
{
    use SoftDeletes;

    protected $table = 'event_registrations';

    protected $primaryKey = 'evrid';

    public $incrementing = true;

    public $timestamps = true;

    protected $keyType = 'int';

    protected $fillable = [
        'athlete_id',
        'event_id',
        'category',
        'division',
        'status',
        'result_medal',
        'result_class_name',
        'result_division',
        'result_category',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }
}
