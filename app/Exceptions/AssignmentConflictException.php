<?php

namespace App\Exceptions;

use Exception;

class AssignmentConflictException extends Exception
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message = 'Cannot assign task to the selected employees.',
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
