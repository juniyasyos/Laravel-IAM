<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiException extends RuntimeException
{
    public function __construct(string $message = '', protected int $statusCode = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
