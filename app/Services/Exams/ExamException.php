<?php

namespace App\Services\Exams;

use RuntimeException;

class ExamException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $finished = false)
    {
        parent::__construct($message);
    }
}
