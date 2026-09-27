<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageMaintenanceRun extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['lease_token'];

    protected $casts = [
        'counters' => 'array',
        'lease_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
