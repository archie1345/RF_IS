<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'logo_url',
        'header_text',
        'footer_text',
        'payment_notes',
    ];
}
