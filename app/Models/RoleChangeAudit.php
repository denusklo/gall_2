<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleChangeAudit extends Model
{
    public const PENDING = 'pending';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    protected $fillable = [
        'actor_uid', 'target_uid', 'action',
        'before_admin', 'before_owner', 'after_admin', 'after_owner',
        'status', 'reason',
    ];

    protected $casts = [
        'before_admin' => 'boolean',
        'before_owner' => 'boolean',
        'after_admin' => 'boolean',
        'after_owner' => 'boolean',
    ];
}
