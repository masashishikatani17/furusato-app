<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JuminRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'kihu_year',
        'version',
        'seq',
        'category',
        'sub_category',
        'city_specified',
        'pref_specified',
        'city_non_specified',
        'pref_non_specified',
        'remark',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'kihu_year' => 'integer',
        'version' => 'integer',
        'seq' => 'integer',
        'city_specified' => 'float',
        'pref_specified' => 'float',
        'city_non_specified' => 'float',
        'pref_non_specified' => 'float',
    ];
}