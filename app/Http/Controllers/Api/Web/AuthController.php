<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use App\Http\Requests\Web\RegisterRequest;
use App\Http\Resources\Web\CustomerResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new customer
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return $this->createdResponse([
                'customer' => new CustomerResource($result['customer']),
                'access_token' => $result['token'],
                'token_type' => $result['token_type']
            ], 'Customer registered successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Registration failed');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred during registration: ' . $e->getMessage());
        }
    }

    /**
     * Login customer with email or phone
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->successResponse([
                'customer' => new CustomerResource($result['customer']),
                'access_token' => $result['token'],
                'token_type' => $result['token_type']
            ], 'Login successful');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Login failed');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred during login');
        }
    }

    /**
     * Logout customer
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $customer = Auth::guard('sanctum')->user();
            
            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            $this->authService->logout($customer);

            return $this->successResponse(null, 'Logout successful');
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred during logout');
        }
    }

    /**
     * Get authenticated customer profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $customer = Auth::guard('sanctum')->user();
            
            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            return $this->successResponse(
                new CustomerResource($customer),
                'Profile retrieved successfully'
            );
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving profile');
        }
    }

    /**
     * Check if customer exists (for validation purposes)
     */
    public function checkCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        $exists = $this->authService->customerExists(
            $request->email,
            $request->phone
        );

        return $this->successResponse([
            'exists' => $exists
        ], 'Customer existence checked');
    }
}
