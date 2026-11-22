<?php

namespace App\Services\Wallet;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PointsTransaction;
use Illuminate\Support\Collection;

class CustomerWalletTransactionLedger
{
    public function relationsLoaded(Customer $customer): bool
    {
        return $customer->relationLoaded('payments')
            || $customer->relationLoaded('walletPaidOrders')
            || $customer->relationLoaded('pointsWalletConversions');
    }

    /**
     * @return array{summary: array{total_in: float, total_out: float, net: float, count: int}, transactions: Collection<int, array<string, mixed>>}
     */
    public function forCustomer(Customer $customer): array
    {
        $payments = $customer->relationLoaded('payments') ? $customer->payments : collect();

        $transactions = $this->fromPayments($payments);
        $transactions = $this->appendWalletPaidOrders($transactions, $payments, $customer);
        $transactions = $this->appendPointsConversions($transactions, $customer);

        $transactions = $transactions
            ->sortByDesc(fn (array $row) => $row['_sort_at'])
            ->map(function (array $row) {
                unset($row['_sort_at']);

                return $row;
            })
            ->values();

        $totalIn = (float) $transactions->where('direction', 'in')->sum('amount');
        $totalOut = (float) $transactions->where('direction', 'out')->sum('amount');

        return [
            'summary' => [
                'total_in' => round($totalIn, 3),
                'total_out' => round($totalOut, 3),
                'net' => round($totalIn - $totalOut, 3),
                'count' => $transactions->count(),
            ],
            'transactions' => $transactions,
        ];
    }

    private function fromPayments(Collection $payments): Collection
    {
        return $payments->map(function (Payment $payment) {
            $direction = $this->paymentDirection($payment);
            $amount = (float) ($payment->total_amount ?? $payment->amount ?? 0);
            $paidAt = $payment->paid_at ?? $payment->created_at;

            return [
                'id' => $payment->id,
                'direction' => $direction,
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
                'order_id' => null,
                'points' => null,
                'paid_at' => \format_datetime_app_tz($paidAt),
                'created_at' => \format_datetime_app_tz($payment->created_at),
                '_sort_at' => $paidAt,
            ];
        });
    }

    private function appendWalletPaidOrders(Collection $transactions, Collection $payments, Customer $customer): Collection
    {
        if (!$customer->relationLoaded('walletPaidOrders')) {
            return $transactions;
        }

        $coveredInvoiceIds = $payments
            ->where('method', 'wallet')
            ->pluck('invoice_id')
            ->filter()
            ->all();
        $coveredReferences = $payments
            ->where('method', 'wallet')
            ->pluck('reference')
            ->filter()
            ->all();

        foreach ($customer->walletPaidOrders as $order) {
            $invoice = $order->invoice;
            if (!$invoice) {
                continue;
            }
            if (in_array($invoice->id, $coveredInvoiceIds, true)) {
                continue;
            }
            if (in_array($order->order_number, $coveredReferences, true)) {
                continue;
            }

            $paidAt = $invoice->paid_at ?? $order->created_at;

            $transactions->push([
                'id' => $order->id,
                'direction' => 'out',
                'type' => Payment::TYPE_ORDER,
                'method' => 'wallet',
                'payment_number' => null,
                'reference' => $order->order_number,
                'src' => 'wallet',
                'status' => Payment::STATUS_COMPLETED,
                'amount' => (float) $invoice->amount_due,
                'charged_amount' => null,
                'bonus_amount' => null,
                'track_id' => null,
                'order_id' => $order->id,
                'points' => null,
                'paid_at' => \format_datetime_app_tz($paidAt),
                'created_at' => \format_datetime_app_tz($order->created_at),
                '_sort_at' => $paidAt,
            ]);
        }

        return $transactions;
    }

    private function appendPointsConversions(Collection $transactions, Customer $customer): Collection
    {
        if (!$customer->relationLoaded('pointsWalletConversions')) {
            return $transactions;
        }

        foreach ($customer->pointsWalletConversions as $conversion) {
            /** @var PointsTransaction $conversion */
            $eventAt = $conversion->created_at;

            $transactions->push([
                'id' => $conversion->id,
                'direction' => 'in',
                'type' => PointsTransaction::TYPE_POINTS_TO_WALLET,
                'method' => 'wallet',
                'payment_number' => null,
                'reference' => $conversion->description,
                'src' => 'points',
                'status' => Payment::STATUS_COMPLETED,
                'amount' => (float) $conversion->amount,
                'charged_amount' => null,
                'bonus_amount' => null,
                'track_id' => null,
                'order_id' => null,
                'points' => (int) $conversion->points,
                'paid_at' => \format_datetime_app_tz($eventAt),
                'created_at' => \format_datetime_app_tz($eventAt),
                '_sort_at' => $eventAt,
            ]);
        }

        return $transactions;
    }

    private function paymentDirection(Payment $payment): string
    {
        if ($payment->type === Payment::TYPE_WALLET_CHARGE) {
            return $payment->status === Payment::STATUS_REFUNDED ? 'out' : 'in';
        }

        if ($payment->status === Payment::STATUS_REFUNDED) {
            return 'in';
        }

        return 'out';
    }
}