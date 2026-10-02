<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageCredential extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'is_default',
        'supabase_url',
        'supabase_key',
        'supabase_service_key',
        'supabase_bucket',
        'vercel_blob_token',
        'vercel_blob_store_url',
        'is_verified',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Encrypt sensitive credentials
        'supabase_url' => 'encrypted',
        'supabase_key' => 'encrypted',
        'supabase_service_key' => 'encrypted',
        'vercel_blob_token' => 'encrypted',
        // Boolean casts
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        // Date casts
        'verified_at' => 'datetime',
    ];

    /**
     * Get the user that owns the credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this credential has complete Supabase configuration.
     */
    public function hasSupabaseConfig(): bool
    {
        return $this->provider === 'supabase'
            && !empty($this->supabase_url)
            && !empty($this->supabase_key);
    }

    /**
     * Check if this credential has complete Vercel Blob configuration.
     */
    public function hasVercelConfig(): bool
    {
        return $this->provider === 'vercel'
            && !empty($this->vercel_blob_token);
    }

    /**
     * Get Supabase credentials as array.
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
     * Get Vercel Blob credentials as array.
     */
    public function getVercelCredentials(): array
    {
        return [
            'token' => $this->vercel_blob_token,
            'store_url' => $this->vercel_blob_store_url ?? 'https://blob.vercel-storage.com',
        ];
    }

    /**
     * Mark credential as verified.
     */
    public function markAsVerified(): void
    {
        $this->is_verified = true;
        $this->verified_at = now();
        $this->save();
    }

    /**
     * Mark credential as unverified.
     */
    public function markAsUnverified(): void
    {
        $this->is_verified = false;
        $this->verified_at = null;
        $this->save();
    }

    /**
     * Set as default credential for the user.
     * Removes default flag from all other credentials for this user.
     */
    public function setAsDefault(): void
    {
        // Remove default from all other credentials for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->is_default = true;
        $this->save();
    }

    /**
     * Scope to get only Supabase credentials.
     */
    public function scopeSupabase($query)
    {
        return $query->where('provider', 'supabase');
    }

    /**
     * Scope to get only Vercel credentials.
     */
    public function scopeVercel($query)
    {
        return $query->where('provider', 'vercel');
    }

    /**
     * Scope to get only default credentials.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get only verified credentials.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Mask sensitive key for display.
     */
    public static function maskKey(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        $length = strlen($key);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 6) . str_repeat('*', $length - 10) . substr($key, -4);
    }
}
