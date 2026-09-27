<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Physical provider account identity. Holds no credentials. */
class StorageAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'identity_key', 'identity_hash', 'trusted_hosted'];

    protected $casts = ['trusted_hosted' => 'boolean'];
}
