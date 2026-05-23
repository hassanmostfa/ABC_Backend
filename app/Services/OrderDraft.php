<?php

namespace App\Services;

class OrderDraft
{
    /**
     * @param array<int, array{offer_id: int, quantity: int}> $offersData
     * @param array<int, \App\Models\Offer> $offersToProcess
     * @param array<int, array{quantity: int}> $offersToAttach
     * @param array<int, array<string, mixed>> $orderItemsData
     * @param array<string, mixed> $orderData
     * @param array<string, mixed> $invoiceAmounts
     */
    public function __construct(
        public readonly array $requestData,
        public readonly array $offersData,
        public readonly array $offersToProcess,
        public readonly array $offersToAttach,
        public readonly array $orderItemsData,
        public readonly float $totalAmount,
        public readonly float $offerDiscount,
        public readonly float $couponsDiscount,
        public readonly ?string $appliedCouponCode,
        public readonly int $usedPoints,
        public readonly float $pointsDiscount,
        public readonly string $deliveryType,
        public readonly array $invoiceAmounts,
        public readonly array $orderData,
        public readonly string $source,
        public readonly ?string $paymentMethod,
        public readonly ?string $paymentGatewaySrc,
    ) {}

    public function amountDue(): float
    {
        return (float) ($this->invoiceAmounts['amountDue'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayloadArray(): array
    {
        return [
            'requestData' => $this->requestData,
            'offersData' => $this->offersData,
            'offersToAttach' => $this->offersToAttach,
            'orderItemsData' => $this->orderItemsData,
            'totalAmount' => $this->totalAmount,
            'offerDiscount' => $this->offerDiscount,
            'couponsDiscount' => $this->couponsDiscount,
            'appliedCouponCode' => $this->appliedCouponCode,
            'usedPoints' => $this->usedPoints,
            'pointsDiscount' => $this->pointsDiscount,
            'deliveryType' => $this->deliveryType,
            'invoiceAmounts' => $this->invoiceAmounts,
            'orderData' => $this->orderData,
            'source' => $this->source,
            'paymentMethod' => $this->paymentMethod,
            'paymentGatewaySrc' => $this->paymentGatewaySrc,
        ];
    }

    public static function fromPayloadArray(array $payload): self
    {
        $offersToProcess = [];
        if (!empty($payload['offersData']) && is_array($payload['offersData'])) {
            foreach ($payload['offersData'] as $offerData) {
                $offerId = (int) ($offerData['offer_id'] ?? 0);
                $quantity = (int) ($offerData['quantity'] ?? 1);
                if ($offerId > 0) {
                    $offer = \App\Models\Offer::find($offerId);
                    if ($offer) {
                        for ($i = 0; $i < $quantity; $i++) {
                            $offersToProcess[] = $offer;
                        }
                    }
                }
            }
        }

        return new self(
            requestData: $payload['requestData'] ?? [],
            offersData: $payload['offersData'] ?? [],
            offersToProcess: $offersToProcess,
            offersToAttach: $payload['offersToAttach'] ?? [],
            orderItemsData: $payload['orderItemsData'] ?? [],
            totalAmount: (float) ($payload['totalAmount'] ?? 0),
            offerDiscount: (float) ($payload['offerDiscount'] ?? 0),
            couponsDiscount: (float) ($payload['couponsDiscount'] ?? 0),
            appliedCouponCode: $payload['appliedCouponCode'] ?? null,
            usedPoints: (int) ($payload['usedPoints'] ?? 0),
            pointsDiscount: (float) ($payload['pointsDiscount'] ?? 0),
            deliveryType: (string) ($payload['deliveryType'] ?? 'pickup'),
            invoiceAmounts: $payload['invoiceAmounts'] ?? [],
            orderData: $payload['orderData'] ?? [],
            source: (string) ($payload['source'] ?? 'call_center'),
            paymentMethod: $payload['paymentMethod'] ?? null,
            paymentGatewaySrc: $payload['paymentGatewaySrc'] ?? null,
        );
    }
}
