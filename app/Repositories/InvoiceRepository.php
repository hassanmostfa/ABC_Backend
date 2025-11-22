<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    protected $model;

    public function __construct(Invoice $invoice)
    {
        $this->model = $invoice;
    }

    /**
     * Get all invoices with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['order', 'order.customer', 'order.charity']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('order', function ($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'LIKE', "%{$search}%")
                                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                      $customerQuery->where('name', 'LIKE', "%{$search}%")
                                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                                  });
                  });
            });
        }

        // Filter by order_id
        if (isset($filters['order_id']) && is_numeric($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        // Filter by status
        if (isset($filters['status']) && !empty(trim($filters['status']))) {
            $query->where('status', $filters['status']);
        }

        // Filter by amount_due range
        if (isset($filters['min_amount']) && is_numeric($filters['min_amount'])) {
            $query->where('amount_due', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount']) && is_numeric($filters['max_amount'])) {
            $query->where('amount_due', '<=', $filters['max_amount']);
        }

        // Filter by date range
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Filter by paid date range
        if (isset($filters['paid_from']) && !empty($filters['paid_from'])) {
            $query->whereDate('paid_at', '>=', $filters['paid_from']);
        }

        if (isset($filters['paid_to']) && !empty($filters['paid_to'])) {
            $query->whereDate('paid_at', '<=', $filters['paid_to']);
        }

        // Sort functionality
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Validate sort fields
        $allowedSortFields = ['invoice_number', 'amount_due', 'status', 'paid_at', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        $allowedSortOrders = ['asc', 'desc'];
        if (!in_array($sortOrder, $allowedSortOrders)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all invoices
     */
    public function getAll(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])->get();
    }

    /**
     * Get invoice by ID
     */
    public function findById(int $id): ?Invoice
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])->find($id);
    }

    /**
     * Get invoice by invoice number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }

    /**
     * Create a new invoice
     */
    public function create(array $data): Invoice
    {
        return $this->model->create($data);
    }

    /**
     * Update invoice
     */
    public function update(int $id, array $data): ?Invoice
    {
        $invoice = $this->findById($id);
        
        if ($invoice) {
            $invoice->update($data);
            return $invoice->fresh(['order', 'order.customer', 'order.charity']);
        }

        return null;
    }

    /**
     * Delete invoice
     */
    public function delete(int $id): bool
    {
        $invoice = $this->findById($id);
        
        if ($invoice) {
            return $invoice->delete();
        }

        return false;
    }

    /**
     * Get invoice by order ID
     */
    public function getByOrder(int $orderId): ?Invoice
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Get invoices by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get paid invoices
     */
    public function getPaid(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('status', 'paid')
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Get pending invoices
     */
    public function getPending(): Collection
    {
        return $this->model->with(['order', 'order.customer', 'order.charity'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

