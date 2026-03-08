<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JpaymentWebhookEvent extends Model
{
    protected $table = 'jpayment_webhook_events';

    protected $guarded = ['id'];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}