<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Setting;
use App\Repositories\Coupons\CouponRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TopCustomerCampaignService
{
    public const SETTING_ENABLED = 'top_customer_coupon_enabled';

    public const SETTING_COUNT = 'top_customer_coupon_count';

    public const SETTING_DISCOUNT_VALUE = 'top_customer_coupon_discount_value';

    public const SETTING_VALID_DAYS = 'top_customer_coupon_valid_days';

    public function __construct(protected CouponRepositoryInterface $couponRepository)
    {
    }

    /**
     * Send one percentage coupon to the customers with the most qualifying orders.
     * The command is scheduled monthly. A customer already rewarded this month is skipped.
     */
    public function sendDueCoupons(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $count = $this->positiveInteger(Setting::getValue(self::SETTING_COUNT, '10'));
        $discountValue = $this->positiveNumber(Setting::getValue(self::SETTING_DISCOUNT_VALUE, '10'), 100);
        $validDays = $this->positiveInteger(Setting::getValue(self::SETTING_VALID_DAYS, '30'));

        if ($count === null || $discountValue === null || $validDays === null) {
            Log::warning('Top-customer coupons skipped because a setting is invalid.', [
                'count' => Setting::getValue(self::SETTING_COUNT),
                'discount_value' => Setting::getValue(self::SETTING_DISCOUNT_VALUE),
                'valid_days' => Setting::getValue(self::SETTING_VALID_DAYS),
            ]);

            return 0;
        }

        $sent = 0;

        foreach ($this->topCustomers($count) as $customer) {
            try {
                $issued = DB::transaction(function () use ($customer, $discountValue, $validDays) {
                    return $this->issueCoupon($customer, $discountValue, $validDays);
                });
                if ($issued) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('Top-customer coupon failed', [
                    'customer_id' => $customer->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function topCustomers(int $count)
    {
        $orderCounts = DB::table('orders')
            ->selectRaw('customer_id, COUNT(*) as orders_count')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', ['cancelled', 'rejected', 'refund'])
            ->groupBy('customer_id');

        return Customer::query()
            ->select('customers.*')
            ->joinSub($orderCounts, 'order_counts', 'order_counts.customer_id', '=', 'customers.id')
            ->where('customers.is_active', true)
            ->where('customers.is_completed', true)
            ->orderByDesc('order_counts.orders_count')
            ->orderBy('customers.id')
            ->limit($count)
            ->get();
    }

    private function issueCoupon(Customer $customer, float $discountValue, int $validDays): bool
    {
        $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
        if (!$locked) {
            return false;
        }

        $alreadySent = Campaign::query()
            ->where('customer_id', $locked->id)
            ->where('type', Campaign::TYPE_TOP_CUSTOMER)
            ->where('sent_at', '>=', now()->startOfMonth())
            ->exists();

        if ($alreadySent) {
            return false;
        }

        Coupon::query()
            ->where('customer_id', $locked->id)
            ->where('type', Coupon::TYPE_TOP_CUSTOMER)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $expiresAt = now()->addDays($validDays);
        $coupon = $this->couponRepository->create([
            'code' => $this->uniqueCode(),
            'type' => Coupon::TYPE_TOP_CUSTOMER,
            'name' => 'Top customer coupon - '.$locked->name,
            'discount_type' => 'percentage',
            'discount_value' => $discountValue,
            'minimum_order_amount' => 0,
            'maximum_discount_amount' => null,
            'usage_limit' => 1,
            'used_count' => 0,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'is_active' => true,
            'customer_id' => $locked->id,
        ]);

        Campaign::create([
            'customer_id' => $locked->id,
            'coupon_id' => $coupon->id,
            'type' => Campaign::TYPE_TOP_CUSTOMER,
            'sent_at' => now(),
        ]);

        $expiresOn = $expiresAt->format('Y-m-d');
        $messageEn = 'Thank you for being one of our top customers. Your coupon code: '.$coupon->code.'. Discount: '.$discountValue.'%. Valid for '.$validDays.' days, until '.$expiresOn.'.';
        $messageAr = 'شكراً لكونك من أفضل عملائنا. كود الكوبون: '.$coupon->code.'. الخصم: '.$discountValue.'%. صالح لمدة '.$validDays.' يوماً حتى '.$expiresOn.'.';

        sendNotification(
            null,
            $locked->id,
            'A reward for you',
            $messageEn,
            'top_customer_coupon',
            [
                'coupon_code' => $coupon->code,
                'coupon_id' => (string) $coupon->id,
                'discount_type' => 'percentage',
                'discount_value' => (string) $discountValue,
                'expires_at' => $expiresOn,
            ],
            'مكافأة لك',
            $messageAr,
            'A reward for you',
            $messageEn
        );

        return true;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TOP'.strtoupper(Str::random(6));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }

    private function isEnabled(): bool
    {
        $enabled = Setting::getValue(self::SETTING_ENABLED, '1');

        return $enabled === '1' || $enabled === 1;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number >= 1 ? $number : null;
    }

    private function positiveNumber(mixed $value, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if ($number <= 0 || $number > $max) {
            return null;
        }

        return $number;
    }
}
