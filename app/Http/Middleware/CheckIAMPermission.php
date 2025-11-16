<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk validasi role berdasarkan IAM token.
 * Gunakan di route: ->middleware('iam.role:admin,doctor')
 * 
 * Note: Permissions adalah tanggung jawab client application.
 * Middleware ini hanya validasi roles dari IAM.
 */
class CheckIAMPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  string|array  $roles  Role slugs yang diperbolehkan
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $userRoles = $request->get('iam_user_roles', []);

        if (empty($userRoles)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No roles found in token',
            ], 403);
        }

        // Extract role slugs from role objects
        $userRoleSlugs = collect($userRoles)->pluck('slug')->toArray();

        // Check if user has any of the required roles
        $hasRole = false;
        foreach ($roles as $role) {
            if (in_array($role, $userRoleSlugs)) {
                $hasRole = true;
                break;
            }
        }

        if (! $hasRole) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Insufficient role. Required: '.implode(' or ', $roles),
                'user_roles' => $userRoleSlugs,
            ], 403);
        }

        return $next($request);
    }
}
