<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Setting;
use App\Repositories\Coupons\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WinbackCampaignService
{
    public const SETTING_ENABLED = 'winback_coupon_enabled';

    public const SETTING_DISCOUNT_VALUE = 'winback_coupon_discount_value';

    public const SETTING_VALID_DAYS = 'winback_coupon_valid_days';

    public const SETTING_INACTIVE_DAYS = 'winback_inactive_days';

    public function __construct(protected CouponRepositoryInterface $couponRepository)
    {
    }

    /**
     * Send one win-back coupon to each customer who has been quiet long enough
     * and has not already received one during this quiet period.
     */
    public function sendDueCoupons(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $discountValue = $this->positiveNumber(Setting::getValue(self::SETTING_DISCOUNT_VALUE, '10'), 100);
        $validDays = $this->positiveInteger(Setting::getValue(self::SETTING_VALID_DAYS, '30'));
        $inactiveDays = $this->positiveInteger(Setting::getValue(self::SETTING_INACTIVE_DAYS, '30'));

        if ($discountValue === null || $validDays === null || $inactiveDays === null) {
            Log::warning('Win-back coupons skipped because a setting is invalid.', [
                'discount_value' => Setting::getValue(self::SETTING_DISCOUNT_VALUE),
                'valid_days' => Setting::getValue(self::SETTING_VALID_DAYS),
                'inactive_days' => Setting::getValue(self::SETTING_INACTIVE_DAYS),
            ]);

            return 0;
        }

        $cutoff = now()->subDays($inactiveDays);
        $sent = 0;
        $lastId = 0;

        do {
            $customers = $this->eligibleCustomers($cutoff)
                ->where('customers.id', '>', $lastId)
                ->orderBy('customers.id')
                ->limit(100)
                ->get();

            foreach ($customers as $customer) {
                $lastId = (int) $customer->id;

                try {
                    $issued = DB::transaction(function () use ($customer, $cutoff, $discountValue, $validDays) {
                        return $this->issueCoupon($customer, $cutoff, $discountValue, $validDays);
                    });
                    if ($issued) {
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Win-back coupon failed', [
                        'customer_id' => $customer->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } while ($customers->count() === 100);

        return $sent;
    }

    /**
     * @return Builder<Customer>
     */
    private function eligibleCustomers(Carbon $cutoff): Builder
    {
        $lastPurchase = $this->lastPurchaseSubquery();

        return Customer::query()
            ->select('customers.*')
            ->leftJoinSub($lastPurchase, 'last_purchase', 'last_purchase.customer_id', '=', 'customers.id')
            ->where('customers.is_active', true)
            ->where('customers.is_completed', true)
            ->whereRaw('COALESCE(last_purchase.last_at, customers.created_at) <= ?', [$cutoff])
            ->whereNotExists(function ($query) use ($cutoff) {
                $query->selectRaw('1')
                    ->from('campaigns')
                    ->whereColumn('campaigns.customer_id', 'customers.id')
                    ->where('campaigns.type', Campaign::TYPE_WINBACK)
                    ->where('campaigns.sent_at', '>', $cutoff);
            });
    }

    private function lastPurchaseSubquery()
    {
        $orders = DB::table('orders')
            ->selectRaw('customer_id, created_at as purchased_at')
            ->whereNotIn('status', ['cancelled', 'rejected', 'refund']);

        $subscriptionOrders = DB::table('subscription_orders')
            ->selectRaw('customer_id, created_at as purchased_at')
            ->whereNotIn('status', ['cancelled', 'rejected']);

        return DB::query()
            ->fromSub($orders->unionAll($subscriptionOrders), 'purchases')
            ->selectRaw('customer_id, MAX(purchased_at) as last_at')
            ->groupBy('customer_id');
    }

    private function issueCoupon(Customer $customer, Carbon $cutoff, float $discountValue, int $validDays): bool
    {
        $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
        if (!$locked) {
            return false;
        }

        $alreadySent = Campaign::query()
            ->where('customer_id', $locked->id)
            ->where('type', Campaign::TYPE_WINBACK)
            ->where('sent_at', '>', $cutoff)
            ->exists();

        if ($alreadySent) {
            return false;
        }

        Coupon::query()
            ->where('customer_id', $locked->id)
            ->where('type', Coupon::TYPE_WINBACK)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $code = $this->uniqueCode();
        $expiresAt = now()->addDays($validDays);

        $coupon = $this->couponRepository->create([
            'code' => $code,
            'type' => Coupon::TYPE_WINBACK,
            'name' => 'Win-back coupon - '.$locked->name,
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
            'type' => Campaign::TYPE_WINBACK,
            'sent_at' => now(),
        ]);

        $expiresOn = $expiresAt->format('Y-m-d');
        $messageEn = 'We miss you. Your coupon code: '.$coupon->code.'. Discount: '.$discountValue.'%. Valid for '.$validDays.' days, until '.$expiresOn.'.';
        $messageAr = 'اشتقنا لك. كود الكوبون: '.$coupon->code.'. الخصم: '.$discountValue.'%. صالح لمدة '.$validDays.' يوماً حتى '.$expiresOn.'.';

        sendNotification(
            null,
            $locked->id,
            'A coupon for you',
            $messageEn,
            'winback_coupon',
            [
                'coupon_code' => $coupon->code,
                'coupon_id' => (string) $coupon->id,
                'discount_type' => 'percentage',
                'discount_value' => (string) $discountValue,
                'expires_at' => $expiresOn,
            ],
            'كوبون لك',
            $messageAr,
            'A coupon for you',
            $messageEn
        );

        return true;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'WINBACK'.strtoupper(Str::random(6));
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
