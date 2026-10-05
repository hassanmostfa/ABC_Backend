<?php

namespace App\Services\Orders;

use App\Jobs\DispatchErpOrderJob;
use App\Jobs\SendOrderCreatedNotificationsJob;
use App\Models\Admin;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\SpecialOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Call-center orders sold at a manually agreed final price. The home delivery manager confirms the
 * request, then the CEO approves it. Nothing is created or sent to ERP until that second approval;
 * the discount that bridges the normal total and the agreed price is frozen into the draft so the
 * CEO approval just replays the regular order pipeline.
 */
class SpecialOrderService
{
    public const CONFIRMATION_PERMISSION = 'special_order_confirmations';

    public const APPROVAL_PERMISSION = 'special_order_approvals';

    public function __construct(
        protected OrderService $orderService,
        protected OrderCheckoutService $orderCheckoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @throws \Exception
     */
    public function create(array $data, ?Admin $admin = null): SpecialOrder
    {
        $finalPrice = round((float) $data['final_price'], 3);
        unset($data['final_price']);

        $data['source'] = 'call_center';
        if ($admin) {
            $data['acting_admin_id'] = $admin->id;
        }
        $data['special_final_price'] = $finalPrice;

        // Prices the order with the special discount applied, and validates stock, minimums and
        // (for wallet) that the customer can cover the discounted amount.
        $draft = $this->orderService->prepareOrderDraft($data);

        $specialDiscount = $draft->specialDiscount;
        $achievedFinalPrice = round($draft->amountDue(), 3);
        $taxRate = (float) Setting::getValue('tax', 0.15);
        $originalAmountDue = round($achievedFinalPrice + ($specialDiscount * (1 + $taxRate)), 3);

        $discountPercentage = $originalAmountDue > 0
            ? round((($originalAmountDue - $achievedFinalPrice) / $originalAmountDue) * 100, 2)
            : 0.00;

        $maxDiscountPercentage = (float) Setting::getValue('special_order_max_discount_percentage', 50);
        if ($discountPercentage > $maxDiscountPercentage) {
            throw new \Exception(
                "This price is a {$discountPercentage}% discount, which exceeds the allowed maximum of {$maxDiscountPercentage}% for special orders.",
                422
            );
        }

        $specialOrder = SpecialOrder::create([
            'order_number' => $this->orderService->generateOrderNumber($draft->source),
            'customer_id' => $data['customer_id'],
            'source' => $draft->source,
            'payment_method' => $draft->paymentMethod,
            'payment_gateway_src' => $draft->paymentGatewaySrc,
            'payload' => $draft->toPayloadArray(),
            'original_amount_due' => $originalAmountDue,
            'final_price' => $achievedFinalPrice,
            'special_discount' => $specialDiscount,
            'discount_percentage' => $discountPercentage,
            'status' => SpecialOrder::STATUS_PENDING,
            'requested_by_id' => $admin?->id,
        ]);

        $this->notifyApprovers($specialOrder, self::CONFIRMATION_PERMISSION, 'manager');

        return $specialOrder;
    }

    /**
     * Home delivery manager confirmation. This does not create the order or send a payment link.
     *
     * @return array{success: bool, message: string, special_order?: SpecialOrder}
     */
    public function confirm(SpecialOrder $specialOrder, ?Admin $admin = null, ?string $notes = null): array
    {
        $updated = SpecialOrder::query()
            ->whereKey($specialOrder->id)
            ->where('status', SpecialOrder::STATUS_PENDING)
            ->update([
                'status' => SpecialOrder::STATUS_MANAGER_CONFIRMED,
                'confirmed_by_id' => $admin?->id,
                'confirmed_at' => now(),
                'confirmation_notes' => $notes,
            ]);

        if ($updated === 0) {
            return ['success' => false, 'message' => 'This special order is not waiting for home delivery manager confirmation.'];
        }

        $specialOrder = $specialOrder->fresh();
        $this->notifyApprovers($specialOrder, self::APPROVAL_PERMISSION, 'ceo');
        $this->notifyRequester($specialOrder, 'confirmed');

        return [
            'success' => true,
            'message' => 'Special order confirmed and sent to the CEO for approval.',
            'special_order' => $specialOrder,
        ];
    }

    /**
     * CEO approval. Cash and wallet orders are created now. Online orders get a payment link;
     * the order itself is created after that payment succeeds.
     *
     * @return array{success: bool, message: string, special_order?: SpecialOrder, payment_link?: ?string}
     * @throws \Exception
     */
    public function approve(SpecialOrder $specialOrder, ?Admin $admin = null, ?string $notes = null): array
    {
        if (!$specialOrder->isManagerConfirmed()) {
            return [
                'success' => false,
                'message' => $specialOrder->isPending()
                    ? 'The home delivery manager must confirm this special order before the CEO can approve it.'
                    : 'This special order has already been reviewed.',
            ];
        }

        $draft = OrderDraft::fromPayloadArray($specialOrder->draft());
        $review = [
            'status' => SpecialOrder::STATUS_APPROVED,
            'reviewed_by_id' => $admin?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ];

        if ($specialOrder->payment_method === 'online_link') {
            $claimed = $this->claimCeoApproval($specialOrder, $review);
            if (!$claimed) {
                return ['success' => false, 'message' => 'This special order has already been reviewed.'];
            }

            try {
                $result = $this->orderCheckoutService->startCheckoutFromDraft(
                    $draft,
                    $specialOrder->customer_id,
                    $specialOrder->source,
                    $specialOrder->payment_gateway_src,
                    $specialOrder->order_number
                );
                $specialOrder->update(['order_checkout_id' => $result['checkout']->id]);
            } catch (\Throwable $e) {
                SpecialOrder::query()
                    ->whereKey($specialOrder->id)
                    ->where('status', SpecialOrder::STATUS_APPROVED)
                    ->whereNull('order_id')
                    ->whereNull('order_checkout_id')
                    ->update([
                        'status' => SpecialOrder::STATUS_MANAGER_CONFIRMED,
                        'reviewed_by_id' => null,
                        'reviewed_at' => null,
                        'review_notes' => null,
                    ]);

                throw $e;
            }

            $this->notifyRequester($specialOrder->fresh(), 'approved');

            return [
                'success' => true,
                'message' => 'Special order approved. Payment link sent to the customer.',
                'special_order' => $specialOrder->fresh(),
                'payment_link' => $result['payment_link'],
            ];
        }

        DB::beginTransaction();

        try {
            $order = $this->orderService->createOrderFromDraft(
                $draft,
                reservedOrderNumber: $specialOrder->order_number
            );
            $claimed = $this->claimCeoApproval($specialOrder, $review + ['order_id' => $order->id]);
            if (!$claimed) {
                throw new \Exception('This special order has already been reviewed.', 400);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DispatchErpOrderJob::dispatchAfterResponse($order->id);
        SendOrderCreatedNotificationsJob::dispatch($order->id)->afterResponse();
        $this->notifyRequester($specialOrder->fresh(), 'approved');

        return [
            'success' => true,
            'message' => 'Special order approved and the order was created.',
            'special_order' => $specialOrder->fresh(),
        ];
    }

    /**
     * @return array{success: bool, message: string, special_order?: SpecialOrder}
     */
    public function reject(SpecialOrder $specialOrder, string $reason, ?Admin $admin = null): array
    {
        if (!$specialOrder->isPending() && !$specialOrder->isManagerConfirmed()) {
            return ['success' => false, 'message' => 'This special order has already been reviewed.'];
        }

        $updated = SpecialOrder::query()
            ->whereKey($specialOrder->id)
            ->whereIn('status', [SpecialOrder::STATUS_PENDING, SpecialOrder::STATUS_MANAGER_CONFIRMED])
            ->update([
                'status' => SpecialOrder::STATUS_REJECTED,
                'reviewed_by_id' => $admin?->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

        if ($updated === 0) {
            return ['success' => false, 'message' => 'This special order has already been reviewed.'];
        }

        $specialOrder = $specialOrder->fresh();
        $this->notifyRequester($specialOrder, 'rejected');

        return [
            'success' => true,
            'message' => 'Special order rejected.',
            'special_order' => $specialOrder->fresh(),
        ];
    }

    /**
     * Apply the CEO decision only while the order is still waiting on that step.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function claimCeoApproval(SpecialOrder $specialOrder, array $attributes): bool
    {
        return SpecialOrder::query()
            ->whereKey($specialOrder->id)
            ->where('status', SpecialOrder::STATUS_MANAGER_CONFIRMED)
            ->update($attributes) > 0;
    }

    /**
     * Ping every active admin who can act on the current approval step.
     */
    protected function notifyApprovers(SpecialOrder $specialOrder, string $permissionSlug, string $step): void
    {
        try {
            $roleIds = RolePermission::query()
                ->where('can_view', true)
                ->whereHas('permissionItem', fn ($query) => $query->where('slug', $permissionSlug))
                ->pluck('role_id');

            if ($roleIds->isEmpty()) {
                return;
            }

            $adminIds = Admin::query()
                ->where('is_active', true)
                ->whereIn('role_id', $roleIds)
                ->pluck('id');

            $priceLine = "{$specialOrder->final_price} KWD instead of {$specialOrder->original_amount_due} KWD ({$specialOrder->discount_percentage}% discount)";
            $priceLineAr = "{$specialOrder->final_price} د.ك بدلاً من {$specialOrder->original_amount_due} د.ك (خصم {$specialOrder->discount_percentage}%)";

            if ($step === 'ceo') {
                $title = 'Special Order CEO Approval Needed';
                $body = "Special order {$specialOrder->order_number} was confirmed by the home delivery manager and needs CEO approval: {$priceLine}.";
                $titleAr = 'طلب خاص بانتظار موافقة المدير التنفيذي';
                $bodyAr = "تم تأكيد الطلب الخاص {$specialOrder->order_number} من مدير التوصيل المنزلي وبانتظار موافقة المدير التنفيذي: {$priceLineAr}.";
            } else {
                $title = 'Special Order Confirmation Needed';
                $body = "Special order {$specialOrder->order_number} needs home delivery manager confirmation: {$priceLine}.";
                $titleAr = 'طلب خاص بانتظار تأكيد مدير التوصيل';
                $bodyAr = "الطلب الخاص {$specialOrder->order_number} بانتظار تأكيد مدير التوصيل المنزلي: {$priceLineAr}.";
            }

            foreach ($adminIds as $adminId) {
                sendNotification(
                    $adminId,
                    null,
                    $title,
                    $body,
                    'special_order',
                    $this->notificationData($specialOrder),
                    $titleAr,
                    $bodyAr
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify special order approvers', [
                'special_order_id' => $specialOrder->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyRequester(SpecialOrder $specialOrder, string $outcome): void
    {
        if (!$specialOrder->requested_by_id) {
            return;
        }

        try {
            $reason = $specialOrder->rejection_reason;
            [$title, $body, $titleAr, $bodyAr] = match ($outcome) {
                'confirmed' => [
                    'Special Order Confirmed',
                    "Special order {$specialOrder->order_number} was confirmed by the home delivery manager and is waiting for CEO approval.",
                    'تم تأكيد الطلب الخاص',
                    "تم تأكيد الطلب الخاص {$specialOrder->order_number} من مدير التوصيل المنزلي وبانتظار موافقة المدير التنفيذي.",
                ],
                'approved' => [
                    'Special Order Approved',
                    "Special order {$specialOrder->order_number} was approved by the CEO.",
                    'تمت الموافقة على الطلب الخاص',
                    "تمت موافقة المدير التنفيذي على الطلب الخاص {$specialOrder->order_number}.",
                ],
                default => [
                    'Special Order Rejected',
                    "Special order {$specialOrder->order_number} was rejected: {$reason}",
                    'تم رفض الطلب الخاص',
                    "تم رفض الطلب الخاص {$specialOrder->order_number}: {$reason}",
                ],
            };

            sendNotification(
                $specialOrder->requested_by_id,
                null,
                $title,
                $body,
                'special_order',
                $this->notificationData($specialOrder),
                $titleAr,
                $bodyAr
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify special order requester', [
                'special_order_id' => $specialOrder->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationData(SpecialOrder $specialOrder): array
    {
        return [
            'special_order_id' => $specialOrder->id,
            'order_number' => $specialOrder->order_number,
            'status' => $specialOrder->status,
            'final_price' => (float) $specialOrder->final_price,
            'original_amount_due' => (float) $specialOrder->original_amount_due,
            'discount_percentage' => (float) $specialOrder->discount_percentage,
        ];
    }
}
