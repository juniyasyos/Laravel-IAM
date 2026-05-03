<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AccessDeniedException extends ApiException
{
    public function __construct(string $message = 'Forbidden.')
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }
}
