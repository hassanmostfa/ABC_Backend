<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new customer
     */
    public function register(array $data): array
    {
        // Check if customer already exists with email or phone
        $existingCustomer = Customer::where('email', $data['email'])
            ->orWhere('phone', $data['phone'])
            ->first();

        if ($existingCustomer) {
            if ($existingCustomer->email === $data['email']) {
                throw ValidationException::withMessages([
                    'email' => ['The email has already been taken.']
                ]);
            }
            if ($existingCustomer->phone === $data['phone']) {
                throw ValidationException::withMessages([
                    'phone' => ['The phone has already been taken.']
                ]);
            }
        }

        // Create new customer
        $customer = Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'points' => 0,
        ]);

        // Generate token
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
        // Determine if login is by email or phone
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        // Find customer by email or phone
        $customer = Customer::where($field, $credentials['login'])->first();

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

        // Delete existing tokens
        $customer->tokens()->delete();

        // Generate new token
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
        // Delete all tokens for the customer
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
        return Customer::where('email', $email)
            ->orWhere('phone', $phone)
            ->exists();
    }

    /**
     * Update customer password
     */
    public function updatePassword(Customer $customer, string $newPassword): bool
    {
        $customer->update([
            'password' => Hash::make($newPassword)
        ]);

        // Delete all existing tokens to force re-login
        $customer->tokens()->delete();

        return true;
    }
}
