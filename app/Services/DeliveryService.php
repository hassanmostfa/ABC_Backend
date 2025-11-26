<?php

namespace App\Services;

use App\Repositories\DeliveryRepositoryInterface;

class DeliveryService
{
    protected $deliveryRepository;

    public function __construct(DeliveryRepositoryInterface $deliveryRepository)
    {
        $this->deliveryRepository = $deliveryRepository;
    }

    /**
     * Create delivery record if delivery type is "delivery"
     *
     * @param int $orderId
     * @param string $deliveryType
     * @param array $data
     * @param string|null $paymentMethod
     * @return void
     */
    public function createDeliveryRecord(
        int $orderId,
        string $deliveryType,
        array $data,
        ?string $paymentMethod = null
    ): void {
        if ($deliveryType !== 'delivery') {
            return;
        }

        $existingDelivery = $this->deliveryRepository->getByOrder($orderId);
        if (!$existingDelivery) {
            $deliveryData = $data['delivery'] ?? [];
            $deliveryData['order_id'] = $orderId;
            $deliveryData['delivery_status'] = $deliveryData['delivery_status'] ?? 'pending';
            // Use payment_method from order level if provided, otherwise from delivery or default to cash
            $deliveryData['payment_method'] = $paymentMethod ?? $deliveryData['payment_method'] ?? 'cash';
            $this->deliveryRepository->create($deliveryData);
        }
    }

    /**
     * Update delivery record if it exists
     *
     * @param int $orderId
     * @param array $data
     * @param string|null $paymentMethod
     * @return void
     */
    public function updateDeliveryRecord(
        int $orderId,
        array $data,
        ?string $paymentMethod = null
    ): void {
        $existingDelivery = $this->deliveryRepository->getByOrder($orderId);
        if ($existingDelivery) {
            $deliveryData = $data['delivery'] ?? [];
            
            // Use payment_method from order level if provided, otherwise from delivery or keep existing
            if ($paymentMethod) {
                $deliveryData['payment_method'] = $paymentMethod;
            } elseif (isset($deliveryData['payment_method'])) {
                // Keep the payment_method from delivery data
            } else {
                // Keep existing payment_method if not provided
                $deliveryData['payment_method'] = $existingDelivery->payment_method;
            }
            
            // Only update if there's data to update
            if (!empty($deliveryData)) {
                $this->deliveryRepository->update($existingDelivery->id, $deliveryData);
            }
        }
    }

    /**
     * Get delivery by order ID
     */
    public function getDeliveryByOrder(int $orderId)
    {
        return $this->deliveryRepository->getByOrder($orderId);
    }
}

