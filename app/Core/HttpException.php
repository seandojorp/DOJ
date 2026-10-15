<?php
declare(strict_types=1);

namespace App\Core;

/** An exception that maps directly to an HTTP error response with a user-safe message. */
class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = []
    ) {
        parent::__construct($message, $status);
    }
}
