<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PaymentCreatorResolver
{
    /**
     * @return array{creator_id: int|null, creator_type: class-string|null}
     */
    public static function resolve(?int $customerId = null): array
    {
        $admin = self::resolveAuthenticatedAdmin();
        if ($admin !== null) {
            return self::forAdmin($admin->id);
        }

        foreach (self::authCandidates() as $candidate) {
            if ($candidate instanceof Customer) {
                return self::forCustomer($candidate->id);
            }
        }

        if ($customerId !== null) {
            return self::forCustomer($customerId);
        }

        return ['creator_id' => null, 'creator_type' => null];
    }

    /**
     * Resolve who created an order. Call-center / admin orders must not use the customer as creator.
     *
     * @return array{creator_id: int|null, creator_type: class-string|null}
     */
    public static function resolveForOrder(?int $customerId, string $source = 'call_center', ?int $actingAdminId = null): array
    {
        if ($actingAdminId !== null && $actingAdminId > 0) {
            return self::forAdmin($actingAdminId);
        }

        $admin = self::resolveAuthenticatedAdmin();
        if ($admin !== null) {
            return self::forAdmin($admin->id);
        }

        if ($source !== 'call_center' && $customerId !== null) {
            return self::forCustomer($customerId);
        }

        return ['creator_id' => null, 'creator_type' => null];
    }

    public static function resolveAuthenticatedAdmin(): ?Admin
    {
        foreach (self::authCandidates() as $candidate) {
            if ($candidate instanceof Admin) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    protected static function authCandidates(): array
    {
        return array_filter([
            Auth::guard('admin')->user(),
            Auth::guard('sanctum')->user(),
            request()->user(),
            Request::user(),
            Auth::user(),
        ]);
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
