<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'actor_user_id' => 'integer',
        'subject_id' => 'integer',
        'meta' => 'array',
    ];
}
