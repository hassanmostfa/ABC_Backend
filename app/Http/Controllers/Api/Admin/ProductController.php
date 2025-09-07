<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\ProductRepositoryInterface;
use App\Repositories\ProductVariantRepositoryInterface;
use App\Http\Resources\ProductResource;
use App\Http\Requests\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    protected $productRepository;
    protected $productVariantRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
    }

    /**
     * Display a listing of the products with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'has_variants' => 'nullable|in:true,false',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
            'has_variants' => $request->input('has_variants'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $products = $this->productRepository->getAllPaginated($filters, $perPage);

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Products retrieved successfully',
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
     * Store a newly created product in storage.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $variants = $validatedData['variants'] ?? [];
        
        // Remove variants from product data
        unset($validatedData['variants']);
        
        $product = $this->productRepository->create($validatedData);

        // Create variants if provided
        if (!empty($variants)) {
            foreach ($variants as $variantData) {
                $variantData['product_id'] = $product->id;
                $this->productVariantRepository->create($variantData);
            }
        }

        // Load the product with variants for response
        $product->load('variants');

        return $this->createdResponse($product, 'Product created successfully');
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->notFoundResponse('Product not found');
        }

        // Load variants with the product
        $product->load('variants');

        // Transform data using ProductResource
        $transformedProduct = new ProductResource($product);

        return $this->resourceResponse($transformedProduct, 'Product retrieved successfully');
    }

    /**
     * Update the specified product in storage.
     */
    public function update(ProductRequest $request, int $id): JsonResponse
    {
        $validatedData = $request->validated();
        $variants = $validatedData['variants'] ?? [];
        
        // Remove variants from product data
        unset($validatedData['variants']);
        
        $product = $this->productRepository->update($id, $validatedData);

        if (!$product) {
            return $this->notFoundResponse('Product not found');
        }

        // Handle variants update
        if (array_key_exists('variants', $request->validated())) {
            // Delete existing variants
            $product->variants()->delete();
            
            // Create new variants if provided
            if (!empty($variants)) {
                foreach ($variants as $variantData) {
                    $variantData['product_id'] = $product->id;
                    $this->productVariantRepository->create($variantData);
                }
            }
        }

        // Load the product with variants for response
        $product->load('variants');

        return $this->updatedResponse($product, 'Product updated successfully');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->productRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Product not found');
        }

        return $this->deletedResponse('Product deleted successfully');
    }

   
    /**
     * Get products by category.
     */
    public function getByCategory(Request $request, int $categoryId): JsonResponse
    {
        $products = $this->productRepository->getByCategory($categoryId);

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products);

        return $this->successResponse($transformedProducts, 'Products by category retrieved successfully');
    }

    /**
     * Get products by subcategory.
     */
    public function getBySubcategory(Request $request, int $subcategoryId): JsonResponse
    {
        $products = $this->productRepository->getBySubcategory($subcategoryId);

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products);

        return $this->successResponse($transformedProducts, 'Products by subcategory retrieved successfully');
    }

    /**
     * Get products with variants.
     */
    public function getWithVariants(Request $request): JsonResponse
    {
        $products = $this->productRepository->getWithVariants();

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products);

        return $this->successResponse($transformedProducts, 'Products with variants retrieved successfully');
    }

    /**
     * Get products without variants.
     */
    public function getWithoutVariants(Request $request): JsonResponse
    {
        $products = $this->productRepository->getWithoutVariants();

        // Transform data using ProductResource
        $transformedProducts = ProductResource::collection($products);

        return $this->successResponse($transformedProducts, 'Products without variants retrieved successfully');
    }
}
