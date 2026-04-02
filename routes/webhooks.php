<?php

use App\Http\Controllers\Webhook\BillingRoboWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/billing-robo/credit-status', [BillingRoboWebhookController::class, 'creditStatus'])
    ->name('billing-robo.credit-status');

Route::post('/billing-robo/bill-issue', [BillingRoboWebhookController::class, 'billIssue'])
    ->name('billing-robo.bill-issue');