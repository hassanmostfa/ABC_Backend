<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class PaymentCreatorResolver
{
    /**
     * @return array{creator_id: int|null, creator_type: class-string|null}
     */
    public static function resolve(?int $customerId = null): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return self::forAdmin($admin->id);
        }

        $tokenUser = Auth::guard('sanctum')->user();
        if ($tokenUser instanceof Admin) {
            return self::forAdmin($tokenUser->id);
        }
        if ($tokenUser instanceof Customer) {
            return self::forCustomer($tokenUser->id);
        }

        $user = Auth::user();
        if ($user instanceof Admin) {
            return self::forAdmin($user->id);
        }
        if ($user instanceof Customer) {
            return self::forCustomer($user->id);
        }

        if ($customerId !== null) {
            return self::forCustomer($customerId);
        }

        return ['creator_id' => null, 'creator_type' => null];
    }

    /**
     * @return array{creator_id: int|null, creator_type: class-string|null}
     */
    public static function fromOrder(Order $order): array
    {
        if ($order->created_by_id && in_array($order->created_by_type, [Admin::class, Customer::class], true)) {
            return [
                'creator_id' => (int) $order->created_by_id,
                'creator_type' => $order->created_by_type,
            ];
        }

        if ($order->customer_id) {
            return self::forCustomer((int) $order->customer_id);
        }

        return self::resolve();
    }

    /**
     * @return array{creator_id: int, creator_type: class-string}
     */
    public static function forAdmin(int $adminId): array
    {
        return [
            'creator_id' => $adminId,
            'creator_type' => Admin::class,
        ];
    }

    /**
     * @return array{creator_id: int, creator_type: class-string}
     */
    public static function forCustomer(int $customerId): array
    {
        return [
            'creator_id' => $customerId,
            'creator_type' => Customer::class,
        ];
    }
}
