<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    /**
     * Get all invoices with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all invoices
     */
    public function getAll(): Collection;

    /**
     * Get invoice by ID
     */
    public function findById(int $id): ?Invoice;

    /**
     * Get invoice by invoice number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;

    /**
     * Create a new invoice
     */
    public function create(array $data): Invoice;

    /**
     * Update invoice
     */
    public function update(int $id, array $data): ?Invoice;

    /**
     * Delete invoice
     */
    public function delete(int $id): bool;

    /**
     * Get invoices by order ID
     */
    public function getByOrder(int $orderId): ?Invoice;

    /**
     * Get invoices by status
     */
    public function getByStatus(string $status): Collection;

    /**
     * Get paid invoices
     */
    public function getPaid(): Collection;

    /**
     * Get pending invoices
     */
    public function getPending(): Collection;
}

