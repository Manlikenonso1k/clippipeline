<?php

namespace App\Services\Connect;

use RuntimeException;
use Throwable;

/**
 * A provider refused to refresh/validate a token. `definitive` = the grant is
 * revoked/invalid (user must reconnect) vs. a transient (network/5xx) failure.
 */
class TokenRefreshException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $definitive = true, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
