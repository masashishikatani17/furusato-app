<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'groups';

    protected $fillable = [
        'company_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_active'  => 'boolean',
    ];
}