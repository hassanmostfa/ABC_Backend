<?php

namespace App\Services;

use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Repositories\CustomerRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PointsTransactionService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected PointsService $pointsService,
        protected WalletService $walletService
    ) {}

    /**
     * Convert points to wallet balance using one_point_money_value setting
     *
     * @param int $customerId
     * @param int $points
     * @return array{success: bool, amount: float, points: int, message?: string}
     */
    public function convertPointsToWallet(int $customerId, int $points): array
    {
        if ($points <= 0 || !$customerId) {
            return [
                'success' => false,
                'amount' => 0.00,
                'points' => 0,
                'message' => 'Points must be greater than zero.',
            ];
        }

        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            return [
                'success' => false,
                'amount' => 0.00,
                'points' => 0,
                'message' => 'Customer not found.',
            ];
        }

        $currentPoints = $customer->points ?? 0;
        if ($currentPoints < $points) {
            return [
                'success' => false,
                'amount' => 0.00,
                'points' => 0,
                'message' => 'Insufficient points. Available: ' . $currentPoints,
            ];
        }

        $onePointValue = (float) Setting::getValue('one_point_money_value', Setting::getValue('one_point_dicount', 0.1)); // money per 1 point
        $amount = round($points * $onePointValue, 2);

        if ($amount <= 0) {
            return [
                'success' => false,
                'amount' => 0.00,
                'points' => 0,
                'message' => 'Minimum conversion amount not met.',
            ];
        }

        try {
            DB::beginTransaction();

            // Deduct points
            $this->pointsService->deductPoints($customerId, $points);

            // Add to wallet
            $this->walletService->addBalance($customerId, $amount);

            // Record transaction
            PointsTransaction::create([
                'customer_id' => $customerId,
                'type' => PointsTransaction::TYPE_POINTS_TO_WALLET,
                'amount' => $amount,
                'points' => $points,
                'description' => "Converted {$points} points to " . number_format($amount, 2) . " in wallet",
            ]);

            DB::commit();

            return [
                'success' => true,
                'amount' => $amount,
                'points' => $points,
                'message' => "Successfully converted {$points} points to " . number_format($amount, 2),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'amount' => 0.00,
                'points' => 0,
                'message' => 'Conversion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record points earned transaction (e.g. from order completion)
     */
    public function recordPointsEarned(int $customerId, int $points, ?int $orderId = null, ?string $description = null): ?PointsTransaction
    {
        if ($points <= 0 || !$customerId) {
            return null;
        }

        return PointsTransaction::create([
            'customer_id' => $customerId,
            'type' => PointsTransaction::TYPE_POINTS_EARNED,
            'amount' => 0,
            'points' => $points,
            'description' => $description ?? "Earned {$points} points",
            'reference_type' => $orderId ? 'App\Models\Order' : null,
            'reference_id' => $orderId,
            'metadata' => $orderId ? ['order_id' => $orderId] : null,
        ]);
    }

    /**
     * Get points transactions for a customer with pagination
     */
    public function getCustomerTransactions(int $customerId, array $filters = [], int $perPage = 15)
    {
        $query = PointsTransaction::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($perPage);
    }
}
