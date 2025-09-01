<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $action = null): Response
    {
        // Get the authenticated admin using Sanctum
        $admin = $request->user();
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 401);
        }

        // Check if admin has the required permission
        if ($action) {
            $hasPermission = $admin->hasPermission($permission, $action);
        } else {
            $hasPermission = $admin->hasAnyPermission($permission);
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
