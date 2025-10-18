<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ShotokuRate extends Model
{
    protected $fillable = [
        'company_id',
        'year',
        'sort',
        'lower',
        'upper',
        'rate',
        'deduction_amount',
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
        'rate' => 'float',
        'deduction_amount' => 'integer',
    ];
}