<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBillingSetting extends Model
{
    protected $fillable = [
        'company_id',
        'payment_method',
        'billing_code',
        'billing_individual_code',
        'payment_method_code',
        'billing_tel',
        'credit_register_status',
        'credit_registered_at',
        'credit_last_error_code',
        'credit_last_error_message',
        'bank_account_type',
        'bank_code',
        'branch_code',
        'bank_account_number',
        'bank_account_name',
    ];

    protected $casts = [
        'credit_registered_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}