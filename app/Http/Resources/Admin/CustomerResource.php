<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use App\Traits\CustomerUnreadNotificationsCountTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    use CustomerUnreadNotificationsCountTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'customer_code' => $this->customer_code,
            'email' => $this->email,
            'points' => (int) ($this->points ?? 0),
            'wallet_balance' => (float) (
                $this->relationLoaded('wallet')
                    ? ($this->wallet->balance ?? 0)
                    : 0
            ),
            'current_language' => $this->current_language ?? 'en',
            'device_platform' => $this->device_platform,
            'app_version' => $this->app_version,
            'unread_notifications_count' => $this->getUnreadNotificationsCount($this->id),
            'is_active' => (bool) $this->is_active,
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'id' => $this->wallet->id,
                    'balance' => (float) $this->wallet->balance,
                ];
            }),
            'wallet_transactions' => $this->whenLoaded('payments', function () {
                $transactions = $this->payments->map(function ($payment) {
                    $direction = $this->walletDirection($payment);
                    $amount = $this->walletAmount($payment, $direction);

                    return [
                        'id' => $payment->id,
                        'direction' => $direction, // in | out
                        'type' => $payment->type,
                        'method' => $payment->method,
                        'payment_number' => $payment->payment_number,
                        'reference' => $payment->reference,
                        'src' => $payment->payment_gateway_src,
                        'status' => $payment->status,
                        'amount' => $amount,
                        'charged_amount' => $payment->type === Payment::TYPE_WALLET_CHARGE ? (float) $payment->amount : null,
                        'bonus_amount' => $payment->type === Payment::TYPE_WALLET_CHARGE ? (float) ($payment->bonus_amount ?? 0) : null,
                        'track_id' => $payment->track_id,
                        'paid_at' => \format_datetime_app_tz($payment->paid_at),
                        'created_at' => \format_datetime_app_tz($payment->created_at),
                    ];
                })->values();

                $totalIn = (float) $transactions
                    ->where('direction', 'in')
                    ->sum('amount');
                $totalOut = (float) $transactions
                    ->where('direction', 'out')
                    ->sum('amount');

                return [
                    'summary' => [
                        'total_in' => round($totalIn, 3),
                        'total_out' => round($totalOut, 3),
                        'net' => round($totalIn - $totalOut, 3),
                        'count' => $transactions->count(),
                    ],
                    'transactions' => $transactions,
                ];
            }),
            'addresses' => $this->whenLoaded('addresses', function () {
                return CustomerAddressResource::collection($this->addresses);
            }),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),
        ];
    }

    private function walletDirection(Payment $payment): string
    {
        return $payment->type === Payment::TYPE_WALLET_CHARGE ? 'in' : 'out';
    }

    private function walletAmount(Payment $payment, string $direction): float
    {
        if ($direction === 'in') {
            return (float) ($payment->total_amount ?? $payment->amount ?? 0);
        }

        return (float) ($payment->total_amount ?? $payment->amount ?? 0);
    }
}

