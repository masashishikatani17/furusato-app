<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'guests';

    // furusato 用の最小構成：必要なカラムのみ許可
    protected $fillable = [
        'name',
        'company_id',
        'group_id',
        'user_id',
        'client_user_id',
        'birth_date',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'group_id'   => 'integer',
        'user_id'    => 'integer',
        'client_user_id' => 'integer',
        'birth_date' => 'date',
    ];

    /** Data との関連（1:N） */
    public function datas()
    {
        return $this->hasMany(Data::class, 'guest_id', 'id');
    }
}