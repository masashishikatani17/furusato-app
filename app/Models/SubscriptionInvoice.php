<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $table = 'subscription_invoices';

    protected $fillable = [
        'company_id',
        'subscription_id',
        'kind',
        'status',
        'demand_code',
        'bill_number',
        'billing_code',
        'item_code',
        'payment_method',
        'quantity',
        'unit_price_yen',
        'months_charged',
        'amount_yen',
        'period_start',
        'period_end',
        'issue_date',
        'due_date',
        'transfer_date',
        'clearing_status',
        'unclearing_amount',
        'last_synced_at',
        'last_sync_error',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',
        'transfer_date' => 'date',
        'last_synced_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
