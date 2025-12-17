<?php

namespace App\Http\Controllers\Api\Mobile\auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\SendOtpRequest;
use App\Http\Requests\Mobile\VerifyOtpRequest;
use App\Http\Requests\Mobile\ResendOtpRequest;
use App\Http\Requests\Mobile\CompleteRegistrationRequest;
use App\Http\Resources\Web\CustomerResource;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseApiController
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Send OTP to customer
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        try {
            $phone = $request->input('phone');
            $phoneCode = $request->input('phone_code', '+965');
            $otpType = $request->input('otp_type', 'login');

            $result = $this->otpService->sendOtp($phone, $phoneCode, $otpType);

            if (!$result['success']) {
                $statusCode = $result['status_code'] ?? 400;
                return response()->json($result, $statusCode);
            }

            return $this->successResponse([
                'verification_token' => $result['verification_token'],
                'expires_at' => $result['expires_at']
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while sending OTP: ' . $e->getMessage());
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $verificationToken = $request->input('verification_token');
            $otpCode = $request->input('otp_code');
            $deviceToken = $request->input('device_token');

            $result = $this->otpService->verifyOtp($verificationToken, $otpCode, $deviceToken);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            $statusCode = $result['status_code'] ?? 200;
            $responseData = [
                'message' => $result['message']
            ];

            // Add token if present
            if (isset($result['token'])) {
                $responseData['access_token'] = $result['token'];
                $responseData['token_type'] = $result['token_type'] ?? 'Bearer';
                $responseData['expires_at'] = $result['expires_at'] ?? null;
            }

            // Add customer if present
            if (isset($result['customer'])) {
                $responseData['customer'] = $result['customer'];
            }

            return response()->json([
                'success' => true,
                ...$responseData
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while verifying OTP: ' . $e->getMessage());
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            $phone = $request->input('phone');
            $phoneCode = $request->input('phone_code', '+965');
            $otpType = $request->input('otp_type', 'login');

            $result = $this->otpService->resendOtp($phone, $phoneCode, $otpType);

            if (!$result['success']) {
                $statusCode = $result['status_code'] ?? 400;
                return response()->json($result, $statusCode);
            }

            return $this->successResponse([
                'verification_token' => $result['verification_token'],
                'expires_at' => $result['expires_at']
            ], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while resending OTP: ' . $e->getMessage());
        }
    }

    /**
     * Complete registration (update customer profile)
     */
    public function completeRegistration(CompleteRegistrationRequest $request): JsonResponse
    {
        try {
            $customer = Auth::guard('sanctum')->user();

            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            // Update customer profile
            $customer->update([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'is_completed' => true,
            ]);

            // Load relationships
            $customer->load(['wallet', 'addresses']);

            return $this->successResponse(
                new CustomerResource($customer),
                'Registration completed successfully'
            );
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while completing registration: ' . $e->getMessage());
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

            // Delete all tokens for the customer
            $customer->tokens()->delete();

            return $this->successResponse(null, 'Logout successful');
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred during logout: ' . $e->getMessage());
        }
    }

    /**
     * Delete customer account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $customer = Auth::guard('sanctum')->user();

            if (!$customer) {
                return $this->unauthorizedResponse('No authenticated customer found');
            }

            // Delete all tokens
            $customer->tokens()->delete();

            // Delete customer (soft delete if implemented, otherwise hard delete)
            $customer->delete();

            return $this->successResponse(null, 'Account deleted successfully');
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while deleting account: ' . $e->getMessage());
        }
    }
}

