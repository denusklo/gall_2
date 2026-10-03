<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Safe-to-display failure of a role operation. Never carries provider text.
 */
class RoleChangeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly bool $partial = false,
        public readonly ?int $auditId = null,
    ) {
        parent::__construct($message);
    }

    public static function denied(string $message = 'Owner privileges required.'): self
    {
        return new self($message, 403);
    }
}
