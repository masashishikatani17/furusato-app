<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FurusatoInput extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'furusato_inputs';

    protected $guarded = [
        'id',
        'data_id',
        'company_id',
        'group_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}