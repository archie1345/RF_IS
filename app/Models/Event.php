<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Event extends Model
{
    use SoftDeletes;

    protected $table = 'events';

    protected $primaryKey = 'event_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'e_name',
        'e_date',
        'location',
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

    protected $dates = ['deleted_at', 'e_date'];

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class, 'event_id', 'event_id');
    }
}
