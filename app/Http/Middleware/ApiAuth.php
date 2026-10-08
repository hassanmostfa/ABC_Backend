<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class ApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!auth('sanctum')->check()) {
                throw new AuthenticationException('Unauthenticated');
            }
        } catch (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        $this->captureCustomerAppInfo($request);

        return $next($request);
    }

    /**
     * Store the last known app platform and version when the mobile app sends them.
     * Missing or invalid headers are ignored so the request still succeeds.
     */
    private function captureCustomerAppInfo(Request $request): void
    {
        $customer = auth('sanctum')->user();
        if (!$customer instanceof Customer) {
            return;
        }

        $updates = [];

        $platform = strtolower(trim((string) $request->header('X-Device-Platform', '')));
        if (in_array($platform, ['ios', 'android'], true) && $customer->device_platform !== $platform) {
            $updates['device_platform'] = $platform;
        }

        $version = trim((string) $request->header('X-App-Version', ''));
        if ($version !== '' && strlen($version) <= 32 && $customer->app_version !== $version) {
            $updates['app_version'] = $version;
        }

        if ($updates === []) {
            return;
        }

        $customer->update($updates);
    }
}
