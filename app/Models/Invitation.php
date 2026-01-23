<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use SoftDeletes;

    protected $table = 'invitations';

    protected $fillable = [
        'company_id',
        'group_id',
        'email',
        'role',
        'token',
        'expires_at',
        'invited_by',
        'accepted_at',
        'cancelled_at',
        'revoked_at',
    ];

    protected $casts = [
        'company_id'    => 'integer',
        'group_id'      => 'integer',
        'invited_by'    => 'integer',
        'expires_at'    => 'datetime',
        'accepted_at'   => 'datetime',
        'cancelled_at'  => 'datetime',
        'revoked_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];
}
