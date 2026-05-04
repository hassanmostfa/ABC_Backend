<?php

namespace App\Http\Controllers\Api;

use App\Services\WarehouseStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseStockController extends BaseApiController
{
    public function __construct(private readonly WarehouseStockService $warehouseStockService)
    {
    }

    /**
     * Get warehouse stock for a given warehouse code.
     * Query parameter: wh_code (optional; defaults to WAREHOUSE_STOCK_DEFAULT_CODE from config)
     */
    public function getStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wh_code' => 'nullable|string|max:50',
        ]);

        $warehouseCode = $validated['wh_code'] ?? config('services.warehouse_stock.default_code', 'FGW1');
        $result = $this->warehouseStockService->getStock($warehouseCode);

        if (!$result['success']) {
            return $this->customResponse([
                'warehouse_response' => $result['body'],
                'warehouse_status' => $result['status'],
            ], $result['error'] ?? 'Failed to fetch warehouse stock', $result['status'] ?? 502);
        }

        return $this->successResponse([
            'warehouse_code' => $warehouseCode,
            'warehouse_response' => $result['body'],
            'warehouse_status' => $result['status'],
        ], 'Warehouse stock fetched successfully');
    }
}
