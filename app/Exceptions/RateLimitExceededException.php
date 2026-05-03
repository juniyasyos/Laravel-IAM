<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class RateLimitExceededException extends ApiException
{
    public function __construct(string $message = 'Too many requests.')
    {
        parent::__construct($message, Response::HTTP_TOO_MANY_REQUESTS);
    }
}
