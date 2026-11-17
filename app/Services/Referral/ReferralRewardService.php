<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralRewardService
{
    /**
     * Award referral points once, after the referred customer has a completed order with a paid invoice.
     */
    public function awardIfQualified(int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }

        $reward = DB::transaction(function () use ($customerId) {
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();

            if (
                !$customer
                || $customer->referred_by === null
                || (int) $customer->referred_by === (int) $customer->id
                || $customer->referral_rewarded_at !== null
            ) {
                return null;
            }

            $hasQualifyingOrder = Order::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->whereHas('invoice', function ($query) {
                    $query->where('status', 'paid');
                })
                ->exists();

            if (!$hasQualifyingOrder) {
                return null;
            }

            $referrer = Customer::query()->whereKey($customer->referred_by)->lockForUpdate()->first();
            if (!$referrer) {
                return null;
            }

            $referralPoints = max(0, (int) Setting::getValue('referral_points', 10));

            $customer->forceFill(['referral_rewarded_at' => now()])->save();

            if ($referralPoints > 0) {
                $referrer->increment('points', $referralPoints);
            }

            return [
                'referrer_id' => $referrer->id,
                'points' => $referralPoints,
                'customer_name' => $customer->name ?: 'A customer',
            ];
        });

        if ($reward === null || $reward['points'] <= 0) {
            return;
        }

        DB::afterCommit(function () use ($reward) {
            $this->notifyReferrer($reward);
        });
    }

    /**
     * @param  array{referrer_id: int, points: int, customer_name: string}  $reward
     */
    private function notifyReferrer(array $reward): void
    {
        try {
            $customerName = $reward['customer_name'];
            $referralPoints = $reward['points'];

            sendNotification(
                customerId: $reward['referrer_id'],
                type: 'referral_reward',
                titleEn: 'Referral Reward',
                titleAr: 'مكافأة الإحالة',
                messageEn: "Congratulations! {$customerName} completed a paid order with your referral code. You earned {$referralPoints} points!",
                messageAr: "تهانينا! أكمل {$customerName} طلباً مدفوعاً باستخدام رمز الإحالة الخاص بك. لقد ربحت {$referralPoints} نقطة!",
                data: [
                    'points_earned' => $referralPoints,
                    'referred_customer_name' => $customerName,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send referral reward notification', [
                'referrer_id' => $reward['referrer_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
