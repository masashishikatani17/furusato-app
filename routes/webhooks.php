<?php

use App\Http\Controllers\Webhook\BillingRoboWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/billing-robo/credit-status', [BillingRoboWebhookController::class, 'creditStatus'])
    ->name('billing-robo.credit-status');