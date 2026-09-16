<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictSpecialOrderPortalToken
{
    public const ABILITY = 'special-order-approver';

    /**
     * Tokens issued by the special-orders portal login may only list, view, approve, and reject
     * special orders. They cannot create special orders or call any other admin API.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->currentAccessToken()) {
            return $next($request);
        }

        $isPortalToken = $user->tokenCan(self::ABILITY) && !$user->tokenCan('admin');
        if (!$isPortalToken) {
            return $next($request);
        }

        if ($this->isAllowedPortalRequest($request)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'This login can only be used to review special orders.',
        ], 403);
    }

    private function isAllowedPortalRequest(Request $request): bool
    {
        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');

        if ($method === 'GET' && in_array($path, ['api/admin/special-orders', 'api/admin/special-orders/me'], true)) {
            return true;
        }

        if ($method === 'POST' && $path === 'api/admin/special-orders/logout') {
            return true;
        }

        if ($method === 'GET' && preg_match('#^api/admin/special-orders/\d+$#', $path)) {
            return true;
        }

        if ($method === 'PATCH' && preg_match('#^api/admin/special-orders/\d+/(approve|reject)$#', $path)) {
            return true;
        }

        return false;
    }
}
