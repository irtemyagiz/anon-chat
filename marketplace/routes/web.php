<?php

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9_]+')
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');
