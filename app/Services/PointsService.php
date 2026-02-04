<?php

namespace App\Services;

use App\Repositories\CustomerRepositoryInterface;
use App\Models\Setting;

class PointsService
{
    protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Calculate points discount
     *
     * @param int $requestedPoints
     * @param float $totalAmount
     * @param float $offerDiscount
     * @return array
     */
    public function calculateDiscount(int $requestedPoints, float $totalAmount, float $offerDiscount): array
    {
        if ($requestedPoints <= 0) {
            return [
                'usedPoints' => 0,
                'pointsDiscount' => 0.00
            ];
        }

        // Get one point money value from settings (used for discount calculation)
        $onePointDiscount = (float) Setting::getValue('one_point_money_value', Setting::getValue('one_point_dicount', 0.1));
        
        // Calculate discount: points * one_point_discount
        $requestedDiscount = $requestedPoints * $onePointDiscount;
        
        // Don't allow points discount to exceed the remaining amount after offer discount
        $remainingAmount = $totalAmount - $offerDiscount;
        $pointsDiscount = min($requestedDiscount, $remainingAmount);
        
        // Recalculate actual points used based on capped discount
        $usedPoints = (int)($pointsDiscount / $onePointDiscount);

        return [
            'usedPoints' => $usedPoints,
            'pointsDiscount' => $pointsDiscount
        ];
    }

    /**
     * Deduct points from customer
     *
     * @param int $customerId
     * @param int $points
     * @return void
     */
    public function deductPoints(int $customerId, int $points): void
    {
        if ($points <= 0 || !$customerId) {
            return;
        }

        $customer = $this->customerRepository->findById($customerId);
        if ($customer) {
            $currentPoints = $customer->points ?? 0;
            $newPoints = max(0, $currentPoints - $points);
            $this->customerRepository->update($customer->id, [
                'points' => $newPoints
            ]);
        }
    }

    /**
     * Refund points to customer
     *
     * @param int $customerId
     * @param int $points
     * @return void
     */
    public function refundPoints(int $customerId, int $points): void
    {
        if ($points <= 0 || !$customerId) {
            return;
        }

        $customer = $this->customerRepository->findById($customerId);
        if ($customer) {
            $currentPoints = $customer->points ?? 0;
            $this->customerRepository->update($customer->id, [
                'points' => $currentPoints + $points
            ]);
        }
    }

    /**
     * Handle points discount for update orders
     *
     * @param array $data
     * @param int $orderCustomerId
     * @param int $oldUsedPoints
     * @param float $totalAmount
     * @param float $offerDiscount
     * @param float|null $existingPointsDiscount
     * @return array
     */
    public function handlePointsUpdate(
        array $data,
        ?int $orderCustomerId,
        int $oldUsedPoints,
        float $totalAmount,
        float $offerDiscount,
        ?float $existingPointsDiscount = null
    ): array {
        $usedPoints = 0;
        $pointsDiscount = 0.00;

        if (isset($data['used_points'])) {
            $requestedPoints = $data['used_points'] ?? 0;
            
            // Refund old points to customer
            if ($oldUsedPoints > 0 && $orderCustomerId) {
                $this->refundPoints($orderCustomerId, $oldUsedPoints);
            }

            // Handle new points if provided
            if ($requestedPoints > 0) {
                $result = $this->calculateDiscount($requestedPoints, $totalAmount, $offerDiscount);
                $usedPoints = $result['usedPoints'];
                $pointsDiscount = $result['pointsDiscount'];
                
                // Deduct new points from customer
                if ($orderCustomerId && $usedPoints > 0) {
                    $this->deductPoints($orderCustomerId, $usedPoints);
                }
            }
        } else {
            // Keep old points if not provided
            $usedPoints = $oldUsedPoints;
            $pointsDiscount = $existingPointsDiscount ?? 0.00;
        }

        return [
            'usedPoints' => $usedPoints,
            'pointsDiscount' => $pointsDiscount
        ];
    }
}

