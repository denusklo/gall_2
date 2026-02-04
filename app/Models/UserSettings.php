<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'storage_provider',
        'supabase_url',
        'supabase_key',
        'supabase_service_key',
        'supabase_bucket',
        'vercel_blob_token',
        'vercel_blob_store_url',
        'credentials_verified',
        'last_verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'supabase_url' => 'encrypted',
        'supabase_key' => 'encrypted',
        'supabase_service_key' => 'encrypted',
        'vercel_blob_token' => 'encrypted',
        'credentials_verified' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    /**
     * Get the user that owns the settings.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if Supabase is configured.
     *
     * @return bool
     */
    public function hasSupabaseConfig(): bool
    {
        return !empty($this->supabase_url) &&
               !empty($this->supabase_key) &&
               !empty($this->supabase_service_key);
    }

    /**
     * Check if Vercel Blob is configured.
     *
     * @return bool
     */
    public function hasVercelConfig(): bool
    {
        return !empty($this->vercel_blob_token);
    }

    /**
     * Get Supabase credentials as an array.
     *
     * @return array
     */
    public function getSupabaseCredentials(): array
    {
        return [
            'url' => $this->supabase_url,
            'key' => $this->supabase_key,
            'service_key' => $this->supabase_service_key,
            'bucket' => $this->supabase_bucket ?? 'images',
        ];
    }

    /**
     * Get Vercel Blob credentials as an array.
     *
     * @return array
     */
    public function getVercelCredentials(): array
    {
        return [
            'token' => $this->vercel_blob_token,
            'store_url' => $this->vercel_blob_store_url ?? 'https://blob.vercel-storage.com',
        ];
    }

    /**
     * Mark credentials as verified.
     *
     * @return bool
     */
    public function markAsVerified(): bool
    {
        $this->credentials_verified = true;
        $this->last_verified_at = now();
        return $this->save();
    }

    /**
     * Mark credentials as unverified.
     *
     * @return bool
     */
    public function markAsUnverified(): bool
    {
        $this->credentials_verified = false;
        $this->last_verified_at = null;
        return $this->save();
    }
}
