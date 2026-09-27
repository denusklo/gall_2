<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Durable upload/delete intent. Written before any remote effect. */
class StorageOperation extends Model
{
    public const UPLOAD = 'upload';
    public const DELETE = 'delete';

    // Terminal states never admit further remote effects.
    public const TERMINAL = ['registered', 'authorization_failed', 'upload_rejected', 'expired_absent', 'finalized'];

    protected $guarded = ['id'];

    protected $hidden = ['lease_token'];

    protected $casts = [
        'metadata' => 'array',
        'expected_size' => 'integer',
        'observed_size' => 'integer',
        'attempt_count' => 'integer',
        'lease_generation' => 'integer',
        'revision' => 'integer',
        'authorization_expires_at' => 'datetime',
        'tombstone_until' => 'datetime',
        'remote_started_at' => 'datetime',
        'last_observed_at' => 'datetime',
        'not_before' => 'datetime',
        'completed_at' => 'datetime',
        'lease_expires_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return in_array($this->state, self::TERMINAL, true);
    }

    /** Safe fields for API/CLI output. */
    public function publicFields(): array
    {
        return ['operation_id' => $this->uuid, 'operation_kind' => $this->kind,
            'operation_state' => $this->state, 'retryable' => !$this->isTerminal() && !in_array($this->state, ['needs_review', 'tombstoned'], true)];
    }
}
