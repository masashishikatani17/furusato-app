<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FurusatoSyoriSetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'furusato_syori_settings';

    protected $guarded = [];

    protected $casts = [
        'data_id' => 'integer',
        'company_id' => 'integer',
        'group_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'payload' => 'array',
    ];
}