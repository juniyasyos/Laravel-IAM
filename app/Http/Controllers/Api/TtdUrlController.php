<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AccessDeniedException;
use App\Exceptions\TtdNotFoundException;
use App\Exceptions\UnauthorizedJwtException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TtdUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TtdUrlController extends Controller
{
    public function show(Request $request, int $userId, TtdUrlService $service): JsonResponse
    {
        if ($userId < 1) {
            throw new AccessDeniedException('Invalid user identifier.');
        }

        $authenticatedUser = $request->user();

        if (! $authenticatedUser instanceof User) {
            throw new UnauthorizedJwtException('Bearer token is missing or invalid.');
        }

        if ($authenticatedUser->id !== $userId) {
            throw new AccessDeniedException('You are not authorized to access this TTD file.');
        }

        $user = User::find($userId);
        if (! $user) {
            throw new TtdNotFoundException('User not found.');
        }

        $url = $service->generatePresignedUrl($user);

        return response()->json([
            'url' => $url,
        ], 200)->header('Cache-Control', 'no-store, private');
    }
}
