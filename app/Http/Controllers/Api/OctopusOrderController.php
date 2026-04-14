<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\StoreOctopusOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Services\OctopusOrderService;
use Illuminate\Http\JsonResponse;

class OctopusOrderController extends BaseApiController
{
    protected OctopusOrderService $octopusOrderService;

    public function __construct(OctopusOrderService $octopusOrderService)
    {
        $this->octopusOrderService = $octopusOrderService;
    }

    /**
     * Create a new order from Octopus integration.
     * 
     * - Finds existing customer by phone or creates new one
     * - Items use short_item instead of variant_id
     * - Order number format: OCT-0001
     * - Delivery date/time: now
     * - src: knet or cc (required for Octopus orders)
     */
    public function store(StoreOctopusOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->octopusOrderService->createOrder($request->validated());

            $order = $result['order'];
            
            if (isset($result['payment_link'])) {
                $order->payment_link = $result['payment_link'];
            }

            $responseData = [
                'order' => new OrderResource($order),
                'customer_created' => $result['customer_created'] ?? false,
            ];

            if (isset($result['payment_link'])) {
                $responseData['payment_link'] = $result['payment_link'];
            }

            return $this->createdResponse($responseData, 'Order created successfully');
        } catch (\Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 100 && $code < 600) ? $code : 500;
            return $this->errorResponse($e->getMessage(), $httpCode);
        }
    }
}
