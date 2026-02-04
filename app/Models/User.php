<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'firebase_id_token',
        'firebase_refresh_token',
        'auth_provider',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'firebase_id_token',
        'firebase_refresh_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Check if user is authenticated via Firebase
     *
     * @return bool
     */
    public function isFirebaseUser(): bool
    {
        return !empty($this->firebase_uid) &&
               in_array($this->auth_provider, ['firebase', 'both']);
    }

    /**
     * Check if user is authenticated via Sanctum/Laravel
     *
     * @return bool
     */
    public function isSanctumUser(): bool
    {
        return in_array($this->auth_provider, ['sanctum', 'both']);
    }

    /**
     * Check if user has both Firebase and Sanctum authentication
     *
     * @return bool
     */
    public function hasDualAuth(): bool
    {
        return $this->auth_provider === 'both' && !empty($this->firebase_uid);
    }

    /**
     * Update Firebase ID token
     *
     * @param string $idToken
     * @param string|null $refreshToken
     * @return bool
     */
    public function updateFirebaseToken(string $idToken, ?string $refreshToken = null): bool
    {
        $this->firebase_id_token = $idToken;

        if ($refreshToken) {
            $this->firebase_refresh_token = $refreshToken;
        }

        // Update auth provider if needed
        if ($this->auth_provider === 'sanctum') {
            $this->auth_provider = 'both';
        }

        return $this->save();
    }

    /**
     * Link Firebase account to this user
     *
     * @param string $firebaseUid
     * @param string $idToken
     * @param string|null $refreshToken
     * @return bool
     */
    public function linkFirebaseAccount(string $firebaseUid, string $idToken, ?string $refreshToken = null): bool
    {
        $this->firebase_uid = $firebaseUid;
        $this->firebase_id_token = $idToken;

        if ($refreshToken) {
            $this->firebase_refresh_token = $refreshToken;
        }

        // Update auth provider
        if ($this->auth_provider === 'sanctum') {
            $this->auth_provider = 'both';
        }

        return $this->save();
    }

    /**
     * Unlink Firebase account from this user
     *
     * @return bool
     */
    public function unlinkFirebaseAccount(): bool
    {
        $this->firebase_uid = null;
        $this->firebase_id_token = null;
        $this->firebase_refresh_token = null;

        if ($this->auth_provider === 'both') {
            $this->auth_provider = 'sanctum';
        }

        return $this->save();
    }

    /**
     * Get the Firebase UID
     *
     * @return string|null
     */
    public function getFirebaseUid(): ?string
    {
        return $this->firebase_uid;
    }

    /**
     * Get the Firebase ID token
     *
     * @return string|null
     */
    public function getFirebaseIdToken(): ?string
    {
        return $this->firebase_id_token;
    }

    /**
     * Get the Firebase refresh token
     *
     * @return string|null
     */
    public function getFirebaseRefreshToken(): ?string
    {
        return $this->firebase_refresh_token;
    }

    /**
     * Get the auth provider
     *
     * @return string
     */
    public function getAuthProvider(): string
    {
        return $this->auth_provider ?? 'sanctum';
    }

    /**
     * Scope to find users by Firebase UID
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $firebaseUid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFirebaseUid($query, string $firebaseUid)
    {
        return $query->where('firebase_uid', $firebaseUid);
    }

    /**
     * Scope to find users by auth provider
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $provider
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAuthProvider($query, string $provider)
    {
        return $query->where('auth_provider', $provider);
    }

    /**
     * Scope to find users with dual auth
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDualAuth($query)
    {
        return $query->where('auth_provider', 'both')
                     ->whereNotNull('firebase_uid');
    }

    /**
     * Get the user's storage settings.
     *
     * @return HasOne
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }
}
