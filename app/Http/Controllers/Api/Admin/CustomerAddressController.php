<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCustomerAddressRequest;
use App\Http\Requests\Admin\UpdateCustomerAddressRequest;
use App\Http\Resources\Admin\CustomerAddressResource;
use App\Repositories\CustomerAddressRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerAddressController extends BaseApiController
{
    protected $customerAddressRepository;

    public function __construct(CustomerAddressRepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    /**
     * Display a listing of customer addresses with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'country_id' => 'nullable|integer|exists:countries,id',
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'sort_by' => 'nullable|in:street,house,block,floor,created_at,updated_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'country_id' => $request->input('country_id'),
            'governorate_id' => $request->input('governorate_id'),
            'area_id' => $request->input('area_id'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $addresses = $this->customerAddressRepository->getAllPaginated($filters, $perPage);

        // Transform addresses using resource
        $transformedAddresses = CustomerAddressResource::collection($addresses->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Customer addresses retrieved successfully',
            'data' => $transformedAddresses,
            'pagination' => [
                'current_page' => $addresses->currentPage(),
                'last_page' => $addresses->lastPage(),
                'per_page' => $addresses->perPage(),
                'total' => $addresses->total(),
                'from' => $addresses->firstItem(),
                'to' => $addresses->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created customer address in storage.
     */
    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        $address = $this->customerAddressRepository->create($request->validated());
        
        // Load relationships
        $address->load(['customer', 'country', 'governorate', 'area']);

        // Log activity
        logAdminActivity('created', 'CustomerAddress', $address->id);

        return $this->createdResponse(new CustomerAddressResource($address), 'Customer address created successfully');
    }

    /**
     * Display the specified customer address.
     */
    public function show(int $id): JsonResponse
    {
        $address = $this->customerAddressRepository->findById($id);

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        return $this->resourceResponse(new CustomerAddressResource($address), 'Customer address retrieved successfully');
    }

    /**
     * Update the specified customer address in storage.
     */
    public function update(UpdateCustomerAddressRequest $request, int $id): JsonResponse
    {
        $address = $this->customerAddressRepository->update($id, $request->validated());

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Log activity
        logAdminActivity('updated', 'CustomerAddress', $id);

        return $this->updatedResponse(new CustomerAddressResource($address), 'Customer address updated successfully');
    }

    /**
     * Remove the specified customer address from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->customerAddressRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Log activity
        logAdminActivity('deleted', 'CustomerAddress', $id);

        return $this->deletedResponse('Customer address deleted successfully');
    }

    /**
     * Get addresses by customer ID.
     */
    public function getByCustomer(int $customerId): JsonResponse
    {
        $addresses = $this->customerAddressRepository->getByCustomer($customerId);

        return response()->json([
            'success' => true,
            'message' => 'Customer addresses retrieved successfully',
            'data' => CustomerAddressResource::collection($addresses),
        ]);
    }
}

