<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Same storage format as the built-in 'encrypted' cast, but tolerant of rows written
 * before the cast was introduced (plaintext values). A plaintext value is returned as-is
 * instead of throwing DecryptException, and is encrypted on the next write.
 */
class TolerantEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value; // legacy plaintext row
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : [$key => Crypt::encryptString((string) $value)];
    }
}
