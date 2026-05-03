<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidApiKeyException extends ApiException
{
    public function __construct(string $message = 'Invalid API key.')
    {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED);
    }
}
