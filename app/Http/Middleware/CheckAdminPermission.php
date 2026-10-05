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

        // A pipe-separated permission list means any one of those permissions is enough.
        $permissions = array_values(array_filter(array_map('trim', explode('|', $permission))));
        $hasPermission = false;

        foreach ($permissions as $permissionSlug) {
            $hasPermission = $action
                ? $user->hasPermission($permissionSlug, $action)
                : $user->hasAnyPermission($permissionSlug);

            if ($hasPermission) {
                break;
            }
        }

        if (!$hasPermission) {
            $required = implode(' or ', $permissions);

            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. You need ' . ($action ? "$action permission for $required" : "permission for $required")
            ], 403);
        }

        return $next($request);
    }
}
