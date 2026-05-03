<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class UnauthorizedJwtException extends ApiException
{
    public function __construct(string $message = 'Unauthorized JWT token.')
    {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED);
    }
}
