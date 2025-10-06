<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShinkokutokureiRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'kihu_year',
        'version',
        'seq',
        'lower',
        'upper',
        'ratio_a',
        'ratio_b',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'kihu_year' => 'integer',
        'version' => 'integer',
        'seq' => 'integer',
        'lower' => 'integer',
        'upper' => 'integer',
        'ratio_a' => 'float',
        'ratio_b' => 'float',
    ];
}