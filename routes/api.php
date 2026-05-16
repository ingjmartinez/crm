<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
