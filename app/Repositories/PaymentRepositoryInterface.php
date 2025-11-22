<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    /**
     * Get all payments with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all payments
     */
    public function getAll(): Collection;

    /**
     * Get payment by ID
     */
    public function findById(int $id): ?Payment;

    /**
     * Get payment by payment number
     */
    public function findByPaymentNumber(string $paymentNumber): ?Payment;

    /**
     * Create a new payment
     */
    public function create(array $data): Payment;

    /**
     * Update payment
     */
    public function update(int $id, array $data): ?Payment;

    /**
     * Delete payment
     */
    public function delete(int $id): bool;

    /**
     * Get payments by invoice ID
     */
    public function getByInvoice(int $invoiceId): Collection;

    /**
     * Get payments by status
     */
    public function getByStatus(string $status): Collection;

    /**
     * Get payments by method
     */
    public function getByMethod(string $method): Collection;

    /**
     * Get completed payments
     */
    public function getCompleted(): Collection;

    /**
     * Get pending payments
     */
    public function getPending(): Collection;
}

