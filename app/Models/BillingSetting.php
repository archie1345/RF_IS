<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    protected $primaryKey = 'billing_setting_id';

    protected $fillable = [
        'name',
        'invoice_day',
        'invoice_time',
        'default_amount',
        'is_active',
    ];

    protected $casts = [
        'invoice_day' => 'integer',
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function monthlyTuition(): self
    {
        return self::query()->firstOrCreate(
            ['name' => 'monthly_tuition'],
            [
                'invoice_day' => 1,
                'invoice_time' => '01:10:00',
                'default_amount' => 150000,
                'is_active' => true,
            ],
        );
    }
}
