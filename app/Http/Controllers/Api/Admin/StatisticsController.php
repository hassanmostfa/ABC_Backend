<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Category;
use App\Models\Charity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class StatisticsController extends BaseApiController
{
    /**
     * Get dashboard statistics: orders (total + by status), counts, and latest 5 orders.
     */
    public function index(): JsonResponse
    {
        $orderStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        $countsByStatus = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $ordersByStatus = [];
        $totalOrders = 0;
        foreach ($orderStatuses as $status) {
            $count = (int) ($countsByStatus[$status] ?? 0);
            $ordersByStatus[$status] = $count;
            $totalOrders += $count;
        }

        $latestOrders = Order::query()
            ->with(['customer', 'charity', 'customerAddress', 'offers', 'items.product', 'items.variant', 'invoice'])
            ->latest('id')
            ->limit(5)
            ->get();

        $totalRevenue = (float) Invoice::query()
            ->where('status', 'paid')
            ->sum('amount_due');

        $data = [
            'orders' => [
                'total' => $totalOrders,
                'by_status' => $ordersByStatus,
            ],
            'total_revenue' => $totalRevenue,
            'customers_count' => Customer::query()->count(),
            'charities_count' => Charity::query()->count(),
            'products_count' => Product::query()->count(),
            'offers_count' => Offer::query()->count(),
            'categories_count' => Category::query()->count(),
            'latest_orders' => OrderResource::collection($latestOrders),
        ];

        return $this->successResponse($data, 'Statistics retrieved successfully');
    }
}
