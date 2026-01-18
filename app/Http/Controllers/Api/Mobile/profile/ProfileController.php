<?php

namespace App\Http\Controllers\Api\Mobile\profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\UpdateProfileRequest;
use App\Http\Resources\Mobile\CustomerResource;
use App\Repositories\CustomerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends BaseApiController
{
    protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get customer profile with all details (mobile API)
     */
    public function show(Request $request): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        // Reload customer with all relationships
        $customer = $this->customerRepository->findById($customer->id);

        if (!$customer) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->successResponse(
            new CustomerResource($customer),
            'Profile retrieved successfully'
        );
    }

    /**
     * Update customer profile (mobile API)
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        // Get authenticated customer
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        try {
            // Get validated data - this includes only fields that were sent and passed validation
            $validatedData = $request->validated();
            
            // Filter out null values to only update provided fields
            $validatedData = array_filter($validatedData, function($value) {
                return $value !== null;
            });

            if (empty($validatedData)) {
                // Check what was actually sent in the request
                $sentData = $request->only(['name', 'email', 'phone']);
                $sentData = array_filter($sentData, function($value) {
                    return $value !== null;
                });
                
                if (empty($sentData)) {
                    return $this->errorResponse('No data provided to update. Please send at least one field: name, email, or phone.', 400);
                } else {
                    // Data was sent but validation failed or filtered out
                    return $this->errorResponse('Validation failed. Please check that name, email, and phone are valid and unique.', 422);
                }
            }

            // Get the customer model directly to ensure we're updating the right instance
            $customerModel = $this->customerRepository->findById($customer->id);
            
            if (!$customerModel) {
                return $this->notFoundResponse('Customer not found');
            }

            // Check if there are any changes
            $hasChanges = false;
            foreach ($validatedData as $key => $value) {
                if ($customerModel->$key != $value) {
                    $hasChanges = true;
                    break;
                }
            }

            if (!$hasChanges) {
                // No changes, just return current profile
                return $this->updatedResponse(
                    new CustomerResource($customerModel),
                    'Profile updated successfully (no changes detected)'
                );
            }

            // Update the customer
            $customerModel->fill($validatedData);
            $customerModel->save();

            // Reload with relationships
            $updatedCustomer = $this->customerRepository->findById($customer->id);

            if (!$updatedCustomer) {
                return $this->errorResponse('Failed to reload updated profile', 500);
            }

            return $this->updatedResponse(
                new CustomerResource($updatedCustomer),
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
}
