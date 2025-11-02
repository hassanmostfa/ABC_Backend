<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\ProductRepositoryInterface;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Resources\Web\WebProductResource;
use App\Http\Resources\Web\WebProductDetailsResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ProductController extends BaseApiController
{
    protected $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Get all products with their variants (public API)
     */
    public function getAllProductsWithVariants(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'active_only' => 'nullable|boolean',
        ]);

        // Prepare filters - only get active products by default for public API
        $filters = [
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
            'status' => $request->input('active_only', true) ? 'active' : null,
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        
        // Get products with variants, categories, and subcategories
        $products = $this->productRepository->getAllPaginated($filters, $perPage);
        
        // Load relationships for all products
        $products->getCollection()->load(['variants' => function ($query) {
            $query->where('is_active', true); // Only active variants for public API
        }, 'category', 'subcategory']);

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Products with variants retrieved successfully',
            'data' => $transformedProducts,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Get all variants as separate products (public API)
     */
    public function getAllVariantsAsProducts(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'active_only' => 'nullable|boolean',
        ]);

        // Prepare filters - only get active products by default for public API
        $filters = [
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
            'status' => $request->input('active_only', true) ? 'active' : null,
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        
        // Get products with variants, categories, and subcategories
        $products = $this->productRepository->getAllPaginated($filters, $perPage);
        
        // Load relationships for all products
        $products->getCollection()->load(['variants' => function ($query) {
            $query->where('is_active', true); // Only active variants for public API
        }, 'category', 'subcategory']);

        // Flatten variants into separate products
        $variantsAsProducts = $this->flattenVariantsAsProducts($products->items());

        // Create pagination for the flattened results
        $totalVariants = $variantsAsProducts->count();
        $currentPage = $products->currentPage();
        $perPage = $products->perPage();
        $offset = ($currentPage - 1) * $perPage;
        $paginatedVariants = $variantsAsProducts->slice($offset, $perPage)->values();

        // Transform data using WebProductResource
        $transformedVariants = WebProductResource::collection($paginatedVariants);

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Variants as separate products retrieved successfully',
            'data' => $transformedVariants,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => ceil($totalVariants / $perPage),
                'per_page' => $perPage,
                'total' => $totalVariants,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $totalVariants),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Get a single product with its variants (public API)
     */
    public function getProductWithVariants(Request $request, int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->notFoundResponse('Product not found');
        }

        // Only return active products for public API
        if (!$product->is_active) {
            return $this->notFoundResponse('Product not found');
        }

        // Load relationships
        $product->load(['variants' => function ($query) {
            $query->where('is_active', true); // Only active variants for public API
        }, 'category', 'subcategory']);

        // Transform data using WebProductDetailsResource
        $transformedProduct = new WebProductDetailsResource($product);

        return $this->successResponse($transformedProduct, 'Product with variants retrieved successfully');
    }

    /**
     * Get products by category with variants (public API)
     */
    public function getProductsByCategoryWithVariants(Request $request, int $categoryId): JsonResponse
    {
        $products = $this->productRepository->getByCategory($categoryId);
        
        // Filter only active products and load relationships
        $activeProducts = $products->filter(function ($product) {
            return $product->is_active;
        })->load(['variants' => function ($query) {
            $query->where('is_active', true); // Only active variants for public API
        }, 'category', 'subcategory']);

        // Transform data using ProductResource
        $transformedProducts = WebProductResource::collection($activeProducts);

        return $this->successResponse($transformedProducts, 'Products by category with variants retrieved successfully');
    }

    /**
     * Get products by subcategory with variants (public API)
     */
    public function getProductsBySubcategoryWithVariants(Request $request, int $subcategoryId): JsonResponse
    {
        $products = $this->productRepository->getBySubcategory($subcategoryId);
        
        // Filter only active products and load relationships
        $activeProducts = $products->filter(function ($product) {
            return $product->is_active;
        })->load(['variants' => function ($query) {
            $query->where('is_active', true); // Only active variants for public API
        }, 'category', 'subcategory']);

        // Transform data using ProductResource
        $transformedProducts = WebProductResource::collection($activeProducts);

        return $this->successResponse($transformedProducts, 'Products by subcategory with variants retrieved successfully');
    }

    /**
     * Flatten variants into separate products
     */
    private function flattenVariantsAsProducts($products): Collection
    {
        $variantsAsProducts = collect();
        
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                // Create a new object that represents the variant as a separate product
                $variantAsProduct = (object) [
                    'id' => $variant->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'name_en' => $product->name_en,
                    'name_ar' => $product->name_ar,
                    'description_en' => $product->description_en,
                    'description_ar' => $product->description_ar,
                    'product_sku' => $product->sku,
                    'variant_sku' => $variant->sku,
                    'size' => $variant->size,
                    'short_item' => $variant->short_item,
                    'quantity' => $variant->quantity,
                    'price' => $variant->price,
                    'image' => $variant->image,
                    'is_active' => $variant->is_active,
                    'category' => $product->category,
                    'subcategory' => $product->subcategory,
                    'created_at' => $variant->created_at,
                    'updated_at' => $variant->updated_at,
                ];
                
                $variantsAsProducts->push($variantAsProduct);
            }
        }
        
        return $variantsAsProducts;
    }
}
