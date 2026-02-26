<?php

namespace App\Http\Controllers\Api\Mobile\products;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Mobile\MobileProductResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseApiController
{
    protected ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
            'status' => 'active',
        ], fn($v) => $v !== null && $v !== '');

        $perPage = $request->input('per_page', 15);
        $products = $this->productRepository->getAllPaginated($filters, $perPage);

        $products->getCollection()->load([
            'variants' => fn($q) => $q->where('is_active', true),
            'category',
            'subcategory',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => MobileProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function getVariantsAsProducts(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
            'status' => 'active',
        ], fn($v) => $v !== null && $v !== '');

        $perPage = $request->input('per_page', 15);
        $products = $this->productRepository->getAllPaginated($filters, $perPage);

        $products->getCollection()->load([
            'variants' => fn($q) => $q->where('is_active', true),
            'category',
            'subcategory',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => MobileProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function getMostSelling(Request $request): JsonResponse
    {
        $topProductIds = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->pluck('product_id')
            ->toArray();

        $products = Product::whereIn('id', $topProductIds)
            ->where('is_active', true)
            ->with([
                'variants' => fn($q) => $q->where('is_active', true),
                'category',
                'subcategory',
            ])
            ->get()
            ->sortBy(fn($p) => array_search($p->id, $topProductIds))
            ->values();

        return $this->successResponse(
            MobileProductResource::collection($products),
            'Most selling products retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product || !$product->is_active) {
            return $this->notFoundResponse('Product not found');
        }

        $product->load([
            'variants' => fn($q) => $q->where('is_active', true),
            'category',
            'subcategory',
        ]);

        return $this->successResponse(
            new MobileProductResource($product),
            'Product retrieved successfully'
        );
    }

    public function getByCategory(int $categoryId): JsonResponse
    {
        $products = $this->productRepository->getByCategory($categoryId);

        $activeProducts = $products->filter(fn($p) => $p->is_active);
        $activeProducts->load([
            'variants' => fn($q) => $q->where('is_active', true),
            'category',
            'subcategory',
        ]);

        return $this->successResponse(
            MobileProductResource::collection($activeProducts->values()),
            'Products retrieved successfully'
        );
    }

    public function getBySubcategory(int $subcategoryId): JsonResponse
    {
        $products = $this->productRepository->getBySubcategory($subcategoryId);

        $activeProducts = $products->filter(fn($p) => $p->is_active);
        $activeProducts->load([
            'variants' => fn($q) => $q->where('is_active', true),
            'category',
            'subcategory',
        ]);

        return $this->successResponse(
            MobileProductResource::collection($activeProducts->values()),
            'Products retrieved successfully'
        );
    }
}
