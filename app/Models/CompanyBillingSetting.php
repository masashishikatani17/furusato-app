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
        'bank_account_type',
        'bank_code',
        'branch_code',
        'bank_account_number',
        'bank_account_name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}