<?php

namespace App\Http\Controllers\Api\Mobile\addresses;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\StoreCustomerAddressRequest;
use App\Http\Requests\Mobile\UpdateCustomerAddressRequest;
use App\Http\Resources\Mobile\CustomerAddressResource;
use App\Repositories\CustomerAddressRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CustomerAddressController extends BaseApiController
{
    protected $customerAddressRepository;

    public function __construct(CustomerAddressRepositoryInterface $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    /**
     * Display a listing of customer addresses (mobile API)
     */
    public function index(Request $request): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Get all addresses for this customer
        $addresses = $this->customerAddressRepository->getByCustomer($customer->id);

        // Load relationships
        $addresses->load(['country', 'governorate', 'area']);

        return $this->successResponse(
            CustomerAddressResource::collection($addresses),
            'Customer addresses retrieved successfully'
        );
    }

    /**
     * Store a newly created customer address (mobile API)
     */
    public function store(StoreCustomerAddressRequest $request): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Merge customer_id from authenticated user
        $addressData = $request->validated();
        $addressData['customer_id'] = $customer->id;

        $address = $this->customerAddressRepository->create($addressData);
        
        // Load relationships
        $address->load(['customer', 'country', 'governorate', 'area']);

        return $this->createdResponse(
            new CustomerAddressResource($address),
            'Customer address created successfully'
        );
    }

    /**
     * Display the specified customer address (mobile API)
     */
    public function show(int $id): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $address = $this->customerAddressRepository->findById($id);

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Ensure the address belongs to the authenticated customer
        if ($address->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to view this address');
        }

        // Load relationships
        $address->load(['country', 'governorate', 'area']);

        return $this->resourceResponse(
            new CustomerAddressResource($address),
            'Customer address retrieved successfully'
        );
    }

    /**
     * Update the specified customer address (mobile API)
     */
    public function update(UpdateCustomerAddressRequest $request, int $id): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $address = $this->customerAddressRepository->findById($id);

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Ensure the address belongs to the authenticated customer
        if ($address->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to update this address');
        }

        $address = $this->customerAddressRepository->update($id, $request->validated());

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Load relationships
        $address->load(['country', 'governorate', 'area']);

        return $this->updatedResponse(
            new CustomerAddressResource($address),
            'Customer address updated successfully'
        );
    }

    /**
     * Remove the specified customer address (mobile API)
     */
    public function destroy(int $id): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $address = $this->customerAddressRepository->findById($id);

        if (!$address) {
            return $this->notFoundResponse('Customer address not found');
        }

        // Ensure the address belongs to the authenticated customer
        if ($address->customer_id !== $customer->id) {
            return $this->unauthorizedResponse('You do not have permission to delete this address');
        }

        $deleted = $this->customerAddressRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Customer address not found');
        }

        return $this->deletedResponse('Customer address deleted successfully');
    }
}
