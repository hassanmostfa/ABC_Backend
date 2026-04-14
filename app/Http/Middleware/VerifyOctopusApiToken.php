<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOctopusApiToken
{
    /**
     * Require a valid Octopus API access token from env (OCTOPUS_API_TOKEN).
     * Token must start with "abc_".
     *
     * Send as: Authorization: Bearer abc_...  or  X-Access-Token: abc_...
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.octopus.access_token');

        if (!is_string($expected) || $expected === '' || !str_starts_with($expected, 'abc_')) {
            return response()->json([
                'success' => false,
                'message' => 'Octopus API is not configured. Set OCTOPUS_API_TOKEN in .env (must start with abc_).',
            ], 503);
        }

        $provided = $request->bearerToken() ?? $request->header('X-Access-Token');

        if (!is_string($provided) || $provided === '') {
            return response()->json([
                'success' => false,
                'message' => 'Access token required. Send Authorization: Bearer abc_... or X-Access-Token header.',
            ], 401);
        }

        if (!str_starts_with($provided, 'abc_')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid access token format.',
            ], 401);
        }

        if (!hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid access token.',
            ], 401);
        }

        return $next($request);
    }
}
