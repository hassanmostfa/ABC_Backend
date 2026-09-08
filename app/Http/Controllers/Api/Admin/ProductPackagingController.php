<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\ProductPackagingRequest;
use App\Http\Resources\Admin\ProductPackagingResource;
use App\Repositories\ProductPackagings\ProductPackagingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPackagingController extends BaseApiController
{
    public function __construct(protected ProductPackagingRepositoryInterface $productPackagingRepository)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'subcategory_id' => $request->input('subcategory_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $perPage = $request->input('per_page', 15);
        $packagings = $this->productPackagingRepository->getAllPaginated($filters, $perPage);

        $response = [
            'success' => true,
            'message' => 'Product packagings retrieved successfully',
            'data' => ProductPackagingResource::collection($packagings->items()),
            'pagination' => [
                'current_page' => $packagings->currentPage(),
                'last_page' => $packagings->lastPage(),
                'per_page' => $packagings->perPage(),
                'total' => $packagings->total(),
                'from' => $packagings->firstItem(),
                'to' => $packagings->lastItem(),
            ],
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    public function store(ProductPackagingRequest $request): JsonResponse
    {
        $packaging = $this->productPackagingRepository->create($request->validated());

        logAdminActivity('created', 'ProductPackaging', $packaging->id);

        return $this->createdResponse(
            new ProductPackagingResource($packaging),
            'Product packaging created successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $packaging = $this->productPackagingRepository->findById($id);

        if (!$packaging) {
            return $this->notFoundResponse('Product packaging not found');
        }

        return $this->resourceResponse(
            new ProductPackagingResource($packaging),
            'Product packaging retrieved successfully'
        );
    }

    public function update(ProductPackagingRequest $request, int $id): JsonResponse
    {
        $packaging = $this->productPackagingRepository->update($id, $request->validated());

        if (!$packaging) {
            return $this->notFoundResponse('Product packaging not found');
        }

        logAdminActivity('updated', 'ProductPackaging', $packaging->id);

        return $this->updatedResponse(
            new ProductPackagingResource($packaging),
            'Product packaging updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $packaging = $this->productPackagingRepository->findById($id);

        if (!$packaging) {
            return $this->notFoundResponse('Product packaging not found');
        }

        $this->productPackagingRepository->delete($id);

        logAdminActivity('deleted', 'ProductPackaging', $id);

        return $this->deletedResponse('Product packaging deleted successfully');
    }

    public function active(): JsonResponse
    {
        $packagings = $this->productPackagingRepository->getActive();

        return $this->successResponse(
            ProductPackagingResource::collection($packagings),
            'Active product packagings retrieved successfully'
        );
    }

    /**
     * Public list of all packagings (no auth, no pagination).
     * Optional subcategory_id returns only packagings used by variants in that subcategory.
     */
    public function all(Request $request): JsonResponse
    {
        $request->validate([
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
        ]);

        $filters = array_filter([
            'subcategory_id' => $request->input('subcategory_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $packagings = $this->productPackagingRepository->getAll($filters);

        return $this->successResponse(
            ProductPackagingResource::collection($packagings),
            'Product packagings retrieved successfully'
        );
    }
}
