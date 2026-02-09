<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Setting;
use App\Http\Resources\Mobile\CustomerResource;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OtpService
{
    protected function getLocale(): string
    {
        $locale = strtolower(request()->header('LANG', request()->input('locale', 'ar')));
        return in_array($locale, ['ar', 'en']) ? $locale : 'ar';
    }

    /**
     * Send OTP to customer
     */
    public function sendOtp(string $phone, string $phoneCode = '+965', string $otpType = 'login'): array
    {
        // Combine phone_code and phone for lookup
        $fullPhone = $phoneCode . $phone;
        // Remove + from phone_code if present for lookup
        $fullPhone = str_replace('+', '', $fullPhone);

        // Delete any existing unused OTPs for this phone to allow new requests
        Otp::where('phone', $phone)
            ->where('phone_code', $phoneCode)
            ->where('otp_type', $otpType)
            ->where('is_used', false)
            ->delete();

        // For login type, check if customer exists and is active
        if ($otpType === 'login') {
            $customer = Customer::where('phone', $fullPhone)->first();

            $locale = $this->getLocale();

            if (!$customer) {
                return [
                    'success' => false,
                    'message' => $locale === 'ar' ? 'المستخدم غير موجود. يرجى التسجيل.' : 'User not found. Please register.',
                    'status_code' => 404
                ];
            }

            if (!$customer->is_active) {
                return [
                    'success' => false,
                    'message' => $locale === 'ar' ? 'الحساب غير نشط. يرجى الاتصال بالدعم.' : 'Account is inactive. Please contact support.'
                ];
            }
        }

        // For register type, check if customer already exists
        if ($otpType === 'register') {
            $existingCustomer = Customer::where('phone', $fullPhone)->first();

            $locale = $this->getLocale();

            if ($existingCustomer) {
                return [
                    'success' => false,
                    'message' => $locale === 'ar' ? 'لديك حساب بالفعل. يرجى تسجيل الدخول.' : 'You already have an account. Please login.',
                    'status_code' => 400
                ];
            }
        }

        // Get OTP settings from database
        $otpExpiry = (int) getSetting('expiry_time', 5);
        $isProduction = getSetting('is_production', '0') === '1';
        $testCode = getSetting('otp_test_code', '1234');

        // Generate OTP code based on production mode
        $otpCode = $isProduction ? $this->generateOtpCode() : $testCode;

        // Create OTP record (store plain OTP code)
        $otp = Otp::create([
            'otp_code' => $otpCode, // Store plain OTP code without hashing
            'otp_type' => $otpType,
            'otp_mode' => 'sms',
            'user_identifier' => $phone,
            'phone_code' => $phoneCode,
            'phone' => $phone,
            'expires_at' => now()->addMinutes($otpExpiry),
            'is_used' => false,
            'generated_by_ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        $locale = $this->getLocale();

        // Prepare response data
        $response = [
            'success' => true,
            'message' => $locale === 'ar' ? 'تم إرسال رمز التحقق بنجاح.' : 'OTP sent successfully.',
            'verification_token' => $otp->id, // Use OTP's UUID as verification token
            'expires_at' => $otp->expires_at->toISOString()
        ];

        // In test mode, include the OTP code in response
        if (!$isProduction) {
            // $response['otp_code'] = $otpCode; // Only in test mode
            $response['message'] = $locale === 'ar' ? 'تم إرسال رمز التحقق بنجاح (وضع الاختبار).' : 'OTP sent successfully (test mode).';
        } else {
            // TODO: Integrate with SMS service for production
            // $this->sendSms($phone, $phoneCode, $otpCode);
        }

        return $response;
    }

    /**
     * Verify OTP using verification token (OTP UUID)
     */
    public function verifyOtp(string $verificationToken, string $otpCode, string $deviceToken = null): array
    {
        // Find the OTP record by UUID (verification token)
        $otp = Otp::where('id', $verificationToken)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        $locale = $this->getLocale();

        if (!$otp) {
            return [
                'success' => false,
                'message' => $locale === 'ar' ? 'رمز التحقق غير صحيح أو منتهي الصلاحية.' : 'OTP is invalid or expired.'
            ];
        }

        // Verify OTP code (compare plain text)
        if ($otp->otp_code !== $otpCode) {
            return [
                'success' => false,
                'message' => $locale === 'ar' ? 'رمز التحقق غير صحيح.' : 'Invalid OTP code.'
            ];
        }

        // Combine phone_code and phone for storage
        $fullPhone = $otp->phone_code . $otp->phone;
        // Remove + from phone_code if present for storage
        $fullPhone = str_replace('+', '', $fullPhone);

        // Find or create customer
        $customer = Customer::where('phone', $fullPhone)->first();

        // For login type, customer must exist
        if ($otp->otp_type === 'login' && !$customer) {
            return [
                'success' => false,
                'message' => $locale === 'ar' ? 'المستخدم غير موجود. يرجى التسجيل.' : 'User not found. Please register.',
                'status_code' => 404
            ];
        }

        // If no customer found, create new customer (only for register type)
        if (!$customer && $otp->otp_type === 'register') {
            $customer = Customer::create([
                'name' => 'Customer ' . Str::random(6),
                'phone' => $fullPhone,
                'is_active' => true,
                'is_completed' => false,
                'points' => 0
            ]);
        }

        // Store device token if provided
        if ($deviceToken) {
            $this->storeDeviceToken($customer->id, $deviceToken);
        }

        // Prepare response data
        $responseData = [];

        // Generate Sanctum token for both login and register types
        if ($otp->otp_type === 'login' || $otp->otp_type === 'register') {
            $token = $customer->createToken('auth-token', ['*'], Carbon::now()->addDays(30));
            $responseData['token'] = $token->plainTextToken;
            $responseData['token_type'] = 'Bearer';
            $responseData['expires_at'] = $token->accessToken->expires_at->toISOString();
        }

        // Handle OTP cleanup based on type
        if ($otp->otp_type === 'register') {
            // Delete OTP record for register type
            $otp->delete();
        } else {
            // Mark OTP as used for other types (login, etc.)
            $otp->update(['is_used' => true]);
        }

        $response = [
            'success' => true,
            'message' => $locale === 'ar' ? 'تم التحقق من رمز التحقق بنجاح.' : 'OTP verified successfully.'
        ];

        // Load customer with all relationships for full mobile customer data
        $customer->load(['wallet', 'addresses']);

        // For login and register, add full customer resource (all user data)
        if ($otp->otp_type === 'login' || $otp->otp_type === 'register') {
            $response['customer'] = new CustomerResource($customer);
        }

        // Merge response data directly into the response
        $response = array_merge($response, $responseData);

        // Add status code for register type to indicate new account
        if ($otp->otp_type === 'register') {
            $response['status_code'] = 201;
            $response['message'] = $locale === 'ar' ? 'تم إنشاء الحساب بنجاح.' : 'Account created successfully.';
        }

        return $response;
    }

    /**
     * Resend OTP
     */
    public function resendOtp(string $phone, string $phoneCode = '+966', string $otpType = 'login'): array
    {
        // Delete any existing unused OTPs for this phone
        Otp::where('phone', $phone)
            ->where('phone_code', $phoneCode)
            ->where('otp_type', $otpType)
            ->where('is_used', false)
            ->delete();

        // Send new OTP
        return $this->sendOtp($phone, $phoneCode, $otpType);
    }

    /**
     * Generate random OTP code
     */
    private function generateOtpCode(): string
    {
        // Get OTP length from test code setting (default to 4 digits)
        $testCode = getSetting('otp_test_code', '1234');
        $otpLength = strlen($testCode);

        $maxValue = pow(10, $otpLength) - 1;
        return str_pad(random_int(0, $maxValue), $otpLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get all OTP settings
     */
    public function getOtpSettings(): array
    {
        return Setting::whereIn('key', ['expiry_time', 'is_production', 'otp_test_code'])
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Check if OTP service is in production mode
     */
    public function isProductionMode(): bool
    {
        return getSetting('is_production', '0') === '1';
    }

    /**
     * Get OTP expiry time in minutes
     */
    public function getOtpExpiryMinutes(): int
    {
        return (int) getSetting('expiry_time', 5);
    }

    /**
     * Get test OTP code
     */
    public function getTestOtpCode(): string
    {
        return getSetting('otp_test_code', '1234');
    }

    /**
     * Check if customer can request new OTP (rate limiting)
     */
    public function canRequestOtp(string $phone, string $phoneCode = '+966'): bool
    {
        $lastOtp = Otp::where('phone', $phone)
            ->where('phone_code', $phoneCode)
            ->latest()
            ->first();

        if (!$lastOtp) {
            return true;
        }

        // Allow new OTP request after 30 seconds
        return $lastOtp->created_at->addSeconds(30)->isPast();
    }

    /**
     * Store device token for customer
     */
    private function storeDeviceToken(int $customerId, string $deviceToken): void
    {
        // Check if device token already exists for this customer
        $existingToken = DeviceToken::where('customer_id', $customerId)
            ->where('token', $deviceToken)
            ->first();

        if (!$existingToken) {
            // Create new device token record
            DeviceToken::create([
                'customer_id' => $customerId,
                'token' => $deviceToken,
            ]);
        } else {
            // Update existing token's updated_at timestamp
            $existingToken->update([
                'updated_at' => now()
            ]);
        }
    }
}

