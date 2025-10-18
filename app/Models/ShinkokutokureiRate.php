<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ShinkokutokureiRate extends Model
{
    protected $fillable = [
        'company_id',
        'year',
        'sort',
        'lower',
        'upper',
        'ratio_a',
        'ratio_b',
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
        'ratio_a' => 'float',
        'ratio_b' => 'float',
    ];
}