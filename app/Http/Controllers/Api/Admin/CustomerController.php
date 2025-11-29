<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\CustomerResource;
use App\Repositories\CustomerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends BaseApiController
{
    protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Display a listing of the customers with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'min_points' => 'nullable|integer|min:0',
            'max_points' => 'nullable|integer|min:0|gte:min_points',
            'sort_by' => 'nullable|in:name,phone,email,points,is_active,created_at,updated_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'min_points' => $request->input('min_points'),
            'max_points' => $request->input('max_points'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $customers = $this->customerRepository->getAllPaginated($filters, $perPage);

        // Transform customers using resource
        $transformedCustomers = CustomerResource::collection($customers->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => $transformedCustomers,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'points' => 'integer|min:0',
        ]);

        $customer = $this->customerRepository->create($request->all());
        
        // Load relationships
        $customer->load(['wallet', 'addresses.country', 'addresses.governorate', 'addresses.area']);

        return $this->createdResponse(new CustomerResource($customer), 'Customer created successfully');
    }

    /**
     * Display the specified customer.
     */
    public function show(int $id): JsonResponse
    {
        $customer = $this->customerRepository->findById($id);

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->resourceResponse(new CustomerResource($customer), 'Customer retrieved successfully');
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'points' => 'integer|min:0',
        ]);

        $customer = $this->customerRepository->update($id, $request->all());

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->updatedResponse(new CustomerResource($customer), 'Customer updated successfully');
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->customerRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->deletedResponse('Customer deleted successfully');
    }

}
