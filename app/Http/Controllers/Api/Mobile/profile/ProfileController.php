<?php

namespace App\Http\Controllers\Api\Mobile\profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ChangePasswordRequest;
use App\Http\Requests\Mobile\UpdateProfileRequest;
use App\Http\Resources\Mobile\CustomerResource;
use App\Models\Customer;
use App\Repositories\CustomerRepositoryInterface;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends BaseApiController
{
    use ManagesFileUploads;
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

            // Handle profile image upload
            if ($request->hasFile('image')) {
                $customerModel = $this->customerRepository->findById($customer->id);
                if ($customerModel && $customerModel->image) {
                    $this->deleteFile($customerModel->image, 'public');
                }
                $imagePath = $this->uploadFile($request->file('image'), Customer::$STORAGE_DIR, 'public');
                $validatedData['image'] = $imagePath;
            }

            // Filter out null values to only update provided fields (except image which may be uploaded)
            $validatedData = array_filter($validatedData, function($value) {
                return $value !== null;
            });

            if (empty($validatedData)) {
                // Check what was actually sent in the request
                $sentData = $request->only(['name', 'email', 'phone', 'image']);
                $sentData = array_filter($sentData, function($value) {
                    return $value !== null;
                });

                if (empty($sentData) && !$request->hasFile('image')) {
                    return $this->errorResponse('No data provided to update. Please send at least one field: name, email, phone, or image.', 400);
                }
                if (empty($validatedData)) {
                    return $this->errorResponse('Validation failed. Please check that name, email, phone are valid and unique, and image is jpeg/png/jpg/gif/webp (max 2MB).', 422);
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

    /**
     * Change customer password (mobile API)
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $customerModel = $this->customerRepository->findById($customer->id);

        if (!$customerModel) {
            return $this->notFoundResponse('Customer not found');
        }

        // Verify old password (OTP users may not have a password set)
        if (!$customerModel->password || !Hash::check($request->old_password, $customerModel->password)) {
            return $this->errorResponse('The current password is incorrect.', 422);
        }

        $customerModel->password = $request->new_password;
        $customerModel->save();

        return $this->successResponse(null, 'Password changed successfully');
    }
}
