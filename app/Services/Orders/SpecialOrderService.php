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
 * Call-center orders sold at a manually agreed final price. Nothing is created or sent to ERP until
 * an approver signs off; the discount that bridges the normal total and the agreed price is frozen
 * into the draft so approval just replays the regular order pipeline.
 */
class SpecialOrderService
{
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

        $this->notifyApprovers($specialOrder);

        return $specialOrder;
    }

    /**
     * @return array{success: bool, message: string, special_order?: SpecialOrder, payment_link?: ?string}
     * @throws \Exception
     */
    public function approve(SpecialOrder $specialOrder, ?Admin $admin = null, ?string $notes = null): array
    {
        if (!$specialOrder->isPending()) {
            return ['success' => false, 'message' => 'This special order has already been reviewed.'];
        }

        $draft = OrderDraft::fromPayloadArray($specialOrder->draft());
        $review = [
            'status' => SpecialOrder::STATUS_APPROVED,
            'reviewed_by_id' => $admin?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ];

        if ($specialOrder->payment_method === 'online_link') {
            $result = $this->orderCheckoutService->startCheckoutFromDraft(
                $draft,
                $specialOrder->customer_id,
                $specialOrder->source,
                $specialOrder->payment_gateway_src,
                $specialOrder->order_number
            );

            $specialOrder->update($review + ['order_checkout_id' => $result['checkout']->id]);
            $this->notifyRequester($specialOrder, approved: true);

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
            $specialOrder->update($review + ['order_id' => $order->id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DispatchErpOrderJob::dispatchAfterResponse($order->id);
        SendOrderCreatedNotificationsJob::dispatch($order->id)->afterResponse();
        $this->notifyRequester($specialOrder, approved: true);

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
        if (!$specialOrder->isPending()) {
            return ['success' => false, 'message' => 'This special order has already been reviewed.'];
        }

        $specialOrder->update([
            'status' => SpecialOrder::STATUS_REJECTED,
            'reviewed_by_id' => $admin?->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notifyRequester($specialOrder, approved: false);

        return [
            'success' => true,
            'message' => 'Special order rejected.',
            'special_order' => $specialOrder->fresh(),
        ];
    }

    /**
     * Ping every active admin whose role can see the approval queue.
     */
    protected function notifyApprovers(SpecialOrder $specialOrder): void
    {
        try {
            $roleIds = RolePermission::query()
                ->where('can_view', true)
                ->whereHas('permissionItem', fn ($query) => $query->where('slug', self::APPROVAL_PERMISSION))
                ->pluck('role_id');

            if ($roleIds->isEmpty()) {
                return;
            }

            $adminIds = Admin::query()
                ->where('is_active', true)
                ->whereIn('role_id', $roleIds)
                ->pluck('id');

            foreach ($adminIds as $adminId) {
                sendNotification(
                    $adminId,
                    null,
                    'Special Order Approval Needed',
                    "Special order {$specialOrder->order_number} needs approval: {$specialOrder->final_price} KWD instead of {$specialOrder->original_amount_due} KWD ({$specialOrder->discount_percentage}% discount).",
                    'special_order',
                    $this->notificationData($specialOrder),
                    'طلب خاص بانتظار الموافقة',
                    "الطلب الخاص {$specialOrder->order_number} بانتظار الموافقة: {$specialOrder->final_price} د.ك بدلاً من {$specialOrder->original_amount_due} د.ك (خصم {$specialOrder->discount_percentage}%)."
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify special order approvers', [
                'special_order_id' => $specialOrder->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyRequester(SpecialOrder $specialOrder, bool $approved): void
    {
        if (!$specialOrder->requested_by_id) {
            return;
        }

        try {
            $reason = $specialOrder->rejection_reason;

            sendNotification(
                $specialOrder->requested_by_id,
                null,
                $approved ? 'Special Order Approved' : 'Special Order Rejected',
                $approved
                    ? "Special order {$specialOrder->order_number} was approved."
                    : "Special order {$specialOrder->order_number} was rejected: {$reason}",
                'special_order',
                $this->notificationData($specialOrder),
                $approved ? 'تمت الموافقة على الطلب الخاص' : 'تم رفض الطلب الخاص',
                $approved
                    ? "تمت الموافقة على الطلب الخاص {$specialOrder->order_number}."
                    : "تم رفض الطلب الخاص {$specialOrder->order_number}: {$reason}"
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
