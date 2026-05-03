<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class TtdNotFoundException extends ApiException
{
    public function __construct(string $message = 'TTD resource not found.')
    {
        parent::__construct($message, Response::HTTP_NOT_FOUND);
    }
}
