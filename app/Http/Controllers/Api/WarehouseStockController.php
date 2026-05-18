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

    /**
     * Test warehouse API connection.
     * Disabled in production to prevent internal network reconnaissance.
     */
    public function testConnection(): JsonResponse
    {
        // Block test endpoint in production
        if (app()->environment('production')) {
            return $this->errorResponse(
                'Test endpoint is disabled in production.',
                403
            );
        }

        $config = config('services.warehouse_stock');
        
        $startTime = microtime(true);
        $result = $this->warehouseStockService->getStock($config['default_code'] ?? 'FGW1');
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return $this->successResponse([
            'config' => [
                'url' => $config['url'],
                'endpoint' => $config['endpoint'],
                'default_code' => $config['default_code'],
                'timeout' => $config['timeout'],
                'connect_timeout' => $config['connect_timeout'],
            ],
            'request_duration_ms' => $duration,
            'result' => $result,
        ], 'Connection test completed');
    }
}
