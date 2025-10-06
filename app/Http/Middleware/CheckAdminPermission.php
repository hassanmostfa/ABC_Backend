<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $action = null): Response
    {
        // Get the authenticated user using Sanctum
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 401);
        }

        // Check if the authenticated user is an Admin
        if (!($user instanceof Admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Admin authentication required.'
            ], 403);
        }

        // Check if admin has the required permission
        if ($action) {
            $hasPermission = $user->hasPermission($permission, $action);
        } else {
            $hasPermission = $user->hasAnyPermission($permission);
        }

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. You need ' . ($action ? "$action permission for $permission" : "permission for $permission")
            ], 403);
        }

        return $next($request);
    }
}
