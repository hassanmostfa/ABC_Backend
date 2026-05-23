<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PendingOnlineInvoiceException;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\CheckoutAsOrderResource;
use App\Models\Order;
use App\Models\OrderCheckout;
use App\Support\OrderCheckoutResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesOrderCheckouts
{
    protected function checkoutResolver(): OrderCheckoutResolver
    {
        return app(OrderCheckoutResolver::class);
    }

    protected function pendingOnlineInvoiceResponse(PendingOnlineInvoiceException $e, Request $request, string $message): JsonResponse
    {
        $pending = [];

        foreach ($e->pendingOrders as $order) {
            $pending[] = (new OrderResource($order))->toArray($request);
        }

        foreach ($e->pendingCheckouts as $checkout) {
            $checkout->loadMissing('customer');
            $pending[] = (new CheckoutAsOrderResource($checkout))->toArray($request);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'pending_invoices' => $pending,
        ], 409);
    }

    protected function orderResponseFromEntity(Order|OrderCheckout $entity): OrderResource|CheckoutAsOrderResource
    {
        if ($entity instanceof OrderCheckout) {
            $entity->loadMissing('customer');

            return new CheckoutAsOrderResource($entity);
        }

        return new OrderResource($entity);
    }

    protected function authorizeCheckoutOrOrder(int $id, int $customerId): Order|OrderCheckout|null
    {
        $entity = $this->checkoutResolver()->resolveCheckoutOrOrder($id);

        if (!$entity) {
            return null;
        }

        if (!$this->checkoutResolver()->belongsToCustomer($entity, $customerId)) {
            return null;
        }

        if ($entity instanceof Order) {
            $entity->load(['customer', 'charity', 'offers', 'items.product', 'items.variant', 'invoice', 'customerAddress']);
        } else {
            $entity->load(['customer']);
        }

        return $entity;
    }
}
