<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class TokureiRate extends Model
{
    protected $fillable = [
        'company_id',
        'year',
        'sort',
        'lower',
        'upper',
        'income_rate',
        'ninety_minus_rate',
        'income_rate_with_recon',
        'tokurei_deduction_rate',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'year' => 'integer',
        'sort' => 'integer',
        'lower' => 'integer',
        'upper' => 'integer',
        'income_rate' => 'float',
        'ninety_minus_rate' => 'float',
        'income_rate_with_recon' => 'float',
        'tokurei_deduction_rate' => 'float',
    ];
}