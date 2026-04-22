<?php

namespace App\Http\Controllers\Api;

use App\Services\ErpOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicErpOrderController extends BaseApiController
{
    public function __construct(private readonly ErpOrderService $erpOrderService)
    {
    }

    /**
     * Public endpoint: accepts ERP order payload and forwards it.
     */
    public function sendOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'OrderNumber' => 'required|string|max:100',
            'OrderDate' => 'required|date_format:Y-m-d',
            'DeliveryDate' => 'required|date_format:Y-m-d',
            'DeliveryValue' => 'required|numeric|min:0',
            'CustomerCode' => 'required|string|max:100',
            'LPO' => 'nullable|string|max:255',
            'Notes' => 'nullable|string',
            'allItems' => 'required|array|min:1',
            'allItems.*.itemCode' => 'required|string|max:100',
            'allItems.*.uom' => 'required|string|max:20',
            'allItems.*.price' => 'required|numeric|min:0',
            'allItems.*.quantity' => 'required|integer|min:1',
            'allItems.*.isFOC' => 'required|boolean',
            'allItems.*.discountAmount' => 'required|numeric|min:0',
            'allItems.*.taxAmount' => 'required|numeric|min:0',
        ]);

        $result = $this->erpOrderService->sendRawOrder($validated);

        if (!$result['success']) {
            return $this->customResponse([
                'erp_response' => $result['body'],
                'erp_status' => $result['status'],
            ], $result['error'] ?? 'Failed to send order to ERP', $result['status'] ?? 502);
        }

        return $this->successResponse([
            'erp_response' => $result['body'],
            'erp_status' => $result['status'],
        ], 'Order sent to ERP successfully');
    }
}
