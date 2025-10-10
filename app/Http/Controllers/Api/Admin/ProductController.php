<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ProductVariant;
use App\Repositories\ProductRepositoryInterface;
use App\Repositories\ProductVariantRepositoryInterface;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Requests\Admin\ProductRequest;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    use ManagesFileUploads;
    
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
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'category_id' => $request->input('category_id'),
            'subcategory_id' => $request->input('subcategory_id'),
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

        // Create variants (required - at least one)
        foreach ($variants as $variantData) {
            $variantData['product_id'] = $product->id;
            
            // Handle variant image upload
            if (isset($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = $this->uploadFile($variantData['image'], ProductVariant::$STORAGE_DIR, 'public');
                $variantData['image'] = $imagePath;
            }
            
            $this->productVariantRepository->create($variantData);
        }

        // Reload the product with variants for response
        $product = $this->productRepository->findById($product->id);

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
        
        // Get current product to preserve category/subcategory if not provided
        $currentProduct = $this->productRepository->findById($id);
        if (!$currentProduct) {
            return $this->notFoundResponse('Product not found');
        }
        
        // Preserve current category and subcategory if not provided in request
        if (!isset($validatedData['category_id'])) {
            $validatedData['category_id'] = $currentProduct->category_id;
        }
        if (!isset($validatedData['subcategory_id'])) {
            $validatedData['subcategory_id'] = $currentProduct->subcategory_id;
        }
        
        $product = $this->productRepository->update($id, $validatedData);

        if (!$product) {
            return $this->notFoundResponse('Product not found');
        }

        // Handle variants update - update existing variants or create new ones
        $existingVariants = $product->variants->keyBy('id');
        $processedVariantIds = [];
        
        foreach ($variants as $index => $variantData) {
            $variantData['product_id'] = $product->id;
            
            // Check if this is an existing variant (has ID) or a new one
            if (isset($variantData['id']) && $existingVariants->has($variantData['id'])) {
                // Update existing variant
                $existingVariant = $existingVariants->get($variantData['id']);
                $processedVariantIds[] = $variantData['id'];
                
                // Handle image update - only if new image is provided
                if (isset($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile) {
                    // Delete old image if exists
                    if ($existingVariant->image) {
                        $this->deleteFile($existingVariant->image, 'public');
                    }
                    // Upload new image
                    $imagePath = $this->uploadFile($variantData['image'], ProductVariant::$STORAGE_DIR, 'public');
                    $variantData['image'] = $imagePath;
                } else {
                    // Keep existing image if no new image provided
                    $variantData['image'] = $existingVariant->image;
                }
                
                // Remove ID from data before updating
                unset($variantData['id']);
                $this->productVariantRepository->update($existingVariant->id, $variantData);
            } else {
                // Create new variant
                unset($variantData['id']); // Remove ID if present
                
                // Handle variant image upload
                if (isset($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $imagePath = $this->uploadFile($variantData['image'], ProductVariant::$STORAGE_DIR, 'public');
                    $variantData['image'] = $imagePath;
                }
                
                $this->productVariantRepository->create($variantData);
            }
        }
        
        // Delete variants that were not included in the update
        $variantsToDelete = $existingVariants->whereNotIn('id', $processedVariantIds);
        foreach ($variantsToDelete as $variantToDelete) {
            // Delete variant image if exists
            if ($variantToDelete->image) {
                $this->deleteFile($variantToDelete->image, 'public');
            }
            $this->productVariantRepository->delete($variantToDelete->id);
        }

        // Reload the product with variants for response
        $product = $this->productRepository->findById($product->id);

        return $this->updatedResponse($product, 'Product updated successfully');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->notFoundResponse('Product not found');
        }

        // Delete associated variant image files
        foreach ($product->variants as $variant) {
            if ($variant->image) {
                $this->deleteFile($variant->image, 'public');
            }
        }

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
