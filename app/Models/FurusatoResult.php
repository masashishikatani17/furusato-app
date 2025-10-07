<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FurusatoResult extends Model
{
    protected $table = 'furusato_results';

    protected $fillable = [
        'data_id',
        'company_id',
        'group_id',
        'payload',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data_id' => 'integer',
        'company_id' => 'integer',
        'group_id' => 'integer',
        'payload' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}