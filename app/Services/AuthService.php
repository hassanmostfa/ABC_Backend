<?php

namespace App\Services;

use App\Models\Customer;
use App\Jobs\DispatchErpCustomerJob;
use App\Services\ErpCustomerService;
use App\Support\KuwaitPhone;
use Illuminate\Support\Facades\Auth;
use App\Services\CouponService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new customer
     */
    public function register(array $data): array
    {
        $data['phone'] = KuwaitPhone::normalize($data['phone'] ?? '');

        $existingByEmail = Customer::where('email', $data['email'])->first();
        if ($existingByEmail) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.']
            ]);
        }

        $existingByPhone = KuwaitPhone::findCustomer($data['phone']);
        if ($existingByPhone) {
            throw ValidationException::withMessages([
                'phone' => ['The phone has already been taken.']
            ]);
        }

        $customer = Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'is_completed' => true,
            'points' => 0,
        ]);

        app(CouponService::class)->createWelcomeCouponForCustomer($customer);

        DispatchErpCustomerJob::dispatchAfterResponse($customer->id, ErpCustomerService::SOURCE_WEB);

        $token = $customer->createToken('auth_token')->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Login customer with email or phone
     */
    public function login(array $credentials): array
    {
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if ($field === 'email') {
            $customer = Customer::where('email', $credentials['login'])->first();
        } else {
            $customer = KuwaitPhone::findCustomer($credentials['login']);
            if ($customer) {
                KuwaitPhone::ensureStoredFormat($customer);
            }
        }

        if (!$customer || !Hash::check($credentials['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.']
            ]);
        }

        if (!$customer->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated. Please contact support.']
            ]);
        }

        $customer->tokens()->delete();

        $token = $customer->createToken('auth_token')->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Logout customer
     */
    public function logout(Customer $customer): bool
    {
        $customer->tokens()->delete();

        return true;
    }

    /**
     * Get authenticated customer
     */
    public function getAuthenticatedCustomer(): ?Customer
    {
        return Auth::guard('sanctum')->user();
    }

    /**
     * Check if customer exists by email or phone
     */
    public function customerExists(string $email, string $phone): bool
    {
        if (Customer::where('email', $email)->exists()) {
            return true;
        }

        return KuwaitPhone::findCustomer($phone) !== null;
    }

    /**
     * Update customer password
     */
    public function updatePassword(Customer $customer, string $newPassword): bool
    {
        $customer->update([
            'password' => Hash::make($newPassword)
        ]);

        $customer->tokens()->delete();

        return true;
    }
}
