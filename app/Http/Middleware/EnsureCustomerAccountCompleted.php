<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccountCompleted
{
    /**
     * Block order creation when the customer account is not completed.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $this->resolveCustomer($request);

        if ($customer && !$customer->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account is not completed. Please complete the account before creating an order.',
            ], 403);
        }

        return $next($request);
    }

    protected function resolveCustomer(Request $request): ?Customer
    {
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user instanceof Customer) {
            return $user;
        }

        $customerId = $request->input('customer_id');
        if ($customerId !== null && $customerId !== '' && is_numeric($customerId)) {
            return Customer::query()->find((int) $customerId);
        }

        return null;
    }
}
