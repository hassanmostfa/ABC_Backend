<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Order;

class PreventUpdateCompletedOrder
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the order ID from the route
        $orderId = $request->route('id');
        
        if ($orderId) {
            $order = Order::find($orderId);
            
            if ($order) {
                // Check if order status is completed or cancelled
                if (in_array($order->status, ['completed', 'cancelled'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot update order. Order status is ' . $order->status . '. Only orders with status "pending" or "processing" can be updated.'
                    ], 422);
                }
            }
        }
        
        return $next($request);
    }
}

