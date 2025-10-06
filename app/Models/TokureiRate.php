<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TokureiRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'kihu_year',
        'version',
        'seq',
        'lower',
        'upper',
        'income_rate',
        'ninety_minus_rate',
        'income_rate_with_recon',
        'tokurei_deduction_rate',
        'note',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'kihu_year' => 'integer',
        'version' => 'integer',
        'seq' => 'integer',
        'lower' => 'integer',
        'upper' => 'integer',
        'income_rate' => 'float',
        'ninety_minus_rate' => 'float',
        'income_rate_with_recon' => 'float',
        'tokurei_deduction_rate' => 'float',
    ];
}