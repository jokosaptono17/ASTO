<?php

namespace App\Services\Mentari\Ai;

use RuntimeException;

class LlmError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}
