<?php

use App\Http\Controllers\Admin\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp-template', [WhatsAppTemplateController::class, 'edit'])
    ->name('whatsapp-template.edit');
Route::put('whatsapp-template', [WhatsAppTemplateController::class, 'update'])
    ->name('whatsapp-template.update');
