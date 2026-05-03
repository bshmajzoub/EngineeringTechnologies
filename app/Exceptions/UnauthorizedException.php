<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user attempts an action on a resource they do not own.
 * Should be reported as HTTP 403 Forbidden, not 409 Conflict.
 */
class UnauthorizedException extends RuntimeException
{
    public function __construct(string $message = 'You are not authorized to perform this action.')
    {
        parent::__construct($message);
    }
}
