<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Data extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'datas';

    protected $fillable = [
        'guest_id',
        'company_id',
        'group_id',
        'user_id',
        'owner_user_id',
        'kihu_year',
        'visibility',
    ];

    protected $casts = [
        'guest_id'      => 'integer',
        'company_id'    => 'integer',
        'group_id'      => 'integer',
        'user_id'       => 'integer',
        'owner_user_id' => 'integer',
        'kihu_year'     => 'integer',
        'visibility'    => 'string',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id', 'id');
    }
}