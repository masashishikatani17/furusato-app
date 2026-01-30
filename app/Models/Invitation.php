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
        'guest_id',
        'guest_name',
        'email',
        'role',
        'token',
        'expires_at',
        'expired_at',
        'invited_by',
        'accepted_at',
        'cancelled_at',
        'revoked_at',
    ];

    protected $casts = [
        'company_id'    => 'integer',
        'group_id'      => 'integer',
        'guest_id'      => 'integer',
        'guest_name'    => 'string',
        'invited_by'    => 'integer',
        'expires_at'    => 'datetime',
        'expired_at'    => 'datetime',
        'accepted_at'   => 'datetime',
        'cancelled_at'  => 'datetime',
        'revoked_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];
}
