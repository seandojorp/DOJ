<?php
declare(strict_types=1);

namespace App\Core;

/** Raised when submitted data fails server-side validation (HTTP 422). */
final class ValidationException extends HttpException
{
    public function __construct(array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct(422, $message, $errors);
    }
}
