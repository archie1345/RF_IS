<?php

namespace App\Models;

use App\Services\PublicContactSettings;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'key',
        'body',
    ];

    protected static function booted(): void
    {
        $clearPublicContactCache = function (MessageTemplate $template): void {
            if (in_array($template->key, [
                PublicContactSettings::CONTACT_KEY,
                PublicContactSettings::BUBBLE_ENABLED_KEY,
            ], true)) {
                app(PublicContactSettings::class)->clearCache();
            }
        };

        static::saved($clearPublicContactCache);
        static::deleted($clearPublicContactCache);
    }
}
