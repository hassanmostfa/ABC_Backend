<?php

namespace App\Services;

use App\Repositories\Customers\CustomerRepositoryInterface;
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
     * Validate wallet balance (locks wallet row when inside a transaction).
     */
    public function validateBalance(?int $customerId, float $amountDue): void
    {
        if (!$customerId) {
            $this->abortWithRollback('Customer ID is required for wallet payment');
        }

        $customer = $this->customerRepository->findById($customerId);
        if (!$customer) {
            $this->abortWithRollback('Customer not found');
        }

        $this->mutateBalance($customerId, function (Wallet $wallet) use ($amountDue) {
            if ((float) $wallet->balance < $amountDue) {
                $this->abortWithRollback(
                    'Insufficient wallet balance. Available: ' . number_format((float) $wallet->balance, 2)
                    . ', Required: ' . number_format($amountDue, 2)
                );
            }
        });
    }

    /**
     * Deduct from wallet balance with row lock to prevent concurrent double-spend.
     */
    public function deductBalance(int $customerId, float $amountDue): void
    {
        if ($amountDue <= 0) {
            return;
        }

        $this->mutateBalance($customerId, function (Wallet $wallet) use ($amountDue) {
            $balance = (float) $wallet->balance;
            if ($balance < $amountDue) {
                throw new \Exception(
                    'Insufficient wallet balance. Available: ' . number_format($balance, 2)
                    . ', Required: ' . number_format($amountDue, 2)
                );
            }
            $wallet->update(['balance' => max(0, $balance - $amountDue)]);
        });
    }

    /**
     * Add balance to wallet (refund / top-up) with row lock.
     */
    public function addBalance(int $customerId, float $amount): void
    {
        if ($amount <= 0 || !$customerId) {
            return;
        }

        $this->mutateBalance($customerId, function (Wallet $wallet) use ($amount) {
            $wallet->update(['balance' => (float) $wallet->balance + $amount]);
        });
    }

    /**
     * Adjust wallet balance atomically (refund old amount, deduct new amount under one lock).
     */
    public function adjustBalance(int $customerId, float $oldAmount, float $newAmount): void
    {
        if (!$customerId) {
            return;
        }

        $this->mutateBalance($customerId, function (Wallet $wallet) use ($oldAmount, $newAmount) {
            $balance = (float) $wallet->balance;

            if ($oldAmount > 0) {
                $balance += $oldAmount;
            }

            if ($newAmount > 0) {
                if ($balance < $newAmount) {
                    throw new \Exception(
                        'Insufficient wallet balance. Available: ' . number_format($balance, 2)
                        . ', Required: ' . number_format($newAmount, 2)
                    );
                }
                $balance -= $newAmount;
            }

            $wallet->update(['balance' => max(0, $balance)]);
        });
    }

    /**
     * Run balance mutation with SELECT ... FOR UPDATE on the wallet row.
     */
    private function mutateBalance(int $customerId, callable $callback): void
    {
        $runner = function () use ($customerId, $callback) {
            $wallet = Wallet::query()
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \Exception('Customer wallet not found');
            }

            $callback($wallet);
        };

        if (DB::transactionLevel() > 0) {
            $runner();
        } else {
            DB::transaction($runner);
        }
    }

    private function abortWithRollback(string $message): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        throw new \Exception($message);
    }
}
