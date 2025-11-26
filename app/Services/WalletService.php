<?php

namespace App\Services;

use App\Repositories\CustomerRepositoryInterface;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Validate wallet balance
     */
    public function validateBalance(?int $customerId, float $amountDue): void
    {
        if (!$customerId) {
            DB::rollBack();
            throw new \Exception('Customer ID is required for wallet payment');
        }

        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            DB::rollBack();
            throw new \Exception('Customer not found');
        }

        $wallet = Wallet::where('customer_id', $customer->id)->first();
        if (!$wallet) {
            DB::rollBack();
            throw new \Exception('Customer wallet not found');
        }

        // Check if wallet has enough balance to cover the order cost (including tax)
        if ($wallet->balance < $amountDue) {
            DB::rollBack();
            throw new \Exception(
                'Insufficient wallet balance. Available: ' . number_format($wallet->balance, 2) . ', Required: ' . number_format($amountDue, 2)
            );
        }
    }

    /**
     * Process wallet payment deduction
     */
    public function deductBalance(int $customerId, float $amountDue): void
    {
        $customer = $this->customerRepository->findById($customerId);
        if ($customer) {
            $wallet = Wallet::where('customer_id', $customer->id)->first();
            if ($wallet) {
                // Deduct order cost from wallet balance (including tax)
                $newBalance = max(0, $wallet->balance - $amountDue);
                $wallet->update(['balance' => $newBalance]);
            }
        }
    }

    /**
     * Add balance to wallet (refund)
     */
    public function addBalance(int $customerId, float $amount): void
    {
        if ($amount <= 0 || !$customerId) {
            return;
        }

        $customer = $this->customerRepository->findById($customerId);
        if ($customer) {
            $wallet = Wallet::where('customer_id', $customer->id)->first();
            if ($wallet) {
                $newBalance = $wallet->balance + $amount;
                $wallet->update(['balance' => $newBalance]);
            }
        }
    }

    /**
     * Adjust wallet balance (for order updates)
     * Refunds old amount and deducts new amount
     */
    public function adjustBalance(int $customerId, float $oldAmount, float $newAmount): void
    {
        if (!$customerId) {
            return;
        }

        // First, refund the old amount that was already deducted
        if ($oldAmount > 0) {
            $this->addBalance($customerId, $oldAmount);
        }
        
        // Then, validate and deduct the new amount
        if ($newAmount > 0) {
            $this->validateBalance($customerId, $newAmount);
            $this->deductBalance($customerId, $newAmount);
        }
    }
}

