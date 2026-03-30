<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'status',
        'applied_at',
        // legacy
        'seats_per_subscription',
        // new SoT fields
        'quantity',
        'term_start',
        'term_end',
        'paid_through',
        'payment_method',
        'billing_code',
        'last_synced_at',
        'quantity_next',
        'cancel_at_term_end',
        'requested_at',
        'billing_robo_managed_recurring',
        'billing_robo_master_demand_code',
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date',
        'paid_through' => 'date',
        'applied_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'requested_at' => 'datetime',
        'cancel_at_term_end' => 'boolean',
        'billing_robo_managed_recurring' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    /**
     * 現在の席上限（5席×口数）
     */
    public function seatLimit(): int
    {
        $q = (int)($this->quantity ?? 0);
        return max(0, $q * 5);
    }
}