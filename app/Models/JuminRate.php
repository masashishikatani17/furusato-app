<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class JuminRate extends Model
{
    protected $fillable = [
        'company_id',
        'year',
        'sort',
        'category',
        'sub_category',
        'city_specified',
        'pref_specified',
        'city_non_specified',
        'pref_non_specified',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'year' => 'integer',
        'sort' => 'integer',
        'city_specified' => 'float',
        'pref_specified' => 'float',
        'city_non_specified' => 'float',
        'pref_non_specified' => 'float',
    ];
}