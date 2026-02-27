<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'status',
        'seats_per_subscription',
        'plan_code',
        'seat_limit',
        'price_yen',
        'paid_through',
        'billing_robo_billing_source_id',
        'billing_robo_billing_code',
        'last_synced_at',
    ];

    protected $casts = [
        'paid_through' => 'date',
        'last_synced_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
