<?php

namespace App\Services;

use App\Repositories\DeliveryRepositoryInterface;
use App\Models\CustomerAddress;
use App\Models\Order;

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
            // Get order to fetch customer_address_id
            $order = Order::with('customerAddress.country', 'customerAddress.governorate', 'customerAddress.area')->find($orderId);
            
            $deliveryData = $data['delivery'] ?? [];
            $deliveryData['order_id'] = $orderId;
            $deliveryData['delivery_status'] = $deliveryData['delivery_status'] ?? 'pending';
            
            // Use payment_method from order level if provided, otherwise from delivery or default to cash
            $deliveryData['payment_method'] = $paymentMethod ?? $deliveryData['payment_method'] ?? 'cash';
            
            // If customer_address_id exists, fetch address from customer_addresses table
            $customerAddressId = $data['customer_address_id'] ?? ($order ? $order->customer_address_id : null);
            
            if ($customerAddressId) {
                $customerAddress = CustomerAddress::with(['country', 'governorate', 'area'])
                    ->find($customerAddressId);
                
                if ($customerAddress) {
                    if (empty($deliveryData['delivery_address'])) {
                        $addressParts = [];
                        if ($customerAddress->country) {
                            $addressParts[] = $customerAddress->country->name_en ?? $customerAddress->country->name_ar;
                        }
                        if ($customerAddress->governorate) {
                            $addressParts[] = $customerAddress->governorate->name_en ?? $customerAddress->governorate->name_ar;
                        }
                        if ($customerAddress->area) {
                            $addressParts[] = $customerAddress->area->name_en ?? $customerAddress->area->name_ar;
                        }
                        $addressParts[] = $customerAddress->formatted_address ?? $customerAddress->street ?? '';
                        $deliveryData['delivery_address'] = implode(', ', array_filter($addressParts));
                    }
                    if (empty($deliveryData['block'])) $deliveryData['block'] = $customerAddress->block;
                    if (empty($deliveryData['street'])) $deliveryData['street'] = $customerAddress->street;
                    if (empty($deliveryData['house_number'])) $deliveryData['house_number'] = $customerAddress->house;
                }
            }
            
            // Set delivery_datetime if not provided
            if (!isset($deliveryData['delivery_datetime'])) {
                $deliveryData['delivery_datetime'] = $deliveryData['delivery_datetime'] ?? now()->addDay();
            }
            
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
            
            // If customer_address_id is provided in order data, fetch address from customer_addresses
            if (isset($data['customer_address_id']) || (isset($data['order']) && isset($data['order']['customer_address_id']))) {
                $customerAddressId = $data['customer_address_id'] ?? $data['order']['customer_address_id'] ?? null;
                
                if ($customerAddressId) {
                    $customerAddress = CustomerAddress::with(['country', 'governorate', 'area'])->find($customerAddressId);
                    
                    if ($customerAddress) {
                        $addressParts = [];
                        if ($customerAddress->country) {
                            $addressParts[] = $customerAddress->country->name_en ?? $customerAddress->country->name_ar;
                        }
                        if ($customerAddress->governorate) {
                            $addressParts[] = $customerAddress->governorate->name_en ?? $customerAddress->governorate->name_ar;
                        }
                        if ($customerAddress->area) {
                            $addressParts[] = $customerAddress->area->name_en ?? $customerAddress->area->name_ar;
                        }
                        $addressParts[] = $customerAddress->formatted_address ?? $customerAddress->street ?? '';
                        $deliveryData['delivery_address'] = implode(', ', array_filter($addressParts));
                        $deliveryData['block'] = $customerAddress->block;
                        $deliveryData['street'] = $customerAddress->street;
                        $deliveryData['house_number'] = $customerAddress->house;
                    }
                }
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

