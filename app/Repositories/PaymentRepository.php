<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    protected $model;

    public function __construct(Payment $payment)
    {
        $this->model = $payment;
    }

    /**
     * Get all payments with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity']);

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                      $invoiceQuery->where('invoice_number', 'LIKE', "%{$search}%")
                                   ->orWhereHas('order', function ($orderQuery) use ($search) {
                                       $orderQuery->where('order_number', 'LIKE', "%{$search}%")
                                                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                                      $customerQuery->where('name', 'LIKE', "%{$search}%")
                                                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                                                  });
                                   });
                  });
            });
        }

        // Filter by invoice_id
        if (isset($filters['invoice_id']) && is_numeric($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        // Filter by status
        if (isset($filters['status']) && !empty(trim($filters['status']))) {
            $query->where('status', $filters['status']);
        }

        // Filter by method
        if (isset($filters['method']) && !empty(trim($filters['method']))) {
            $query->where('method', $filters['method']);
        }

        // Filter by amount range
        if (isset($filters['min_amount']) && is_numeric($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount']) && is_numeric($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
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
        $allowedSortFields = ['payment_number', 'amount', 'method', 'status', 'paid_at', 'created_at', 'updated_at'];
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
     * Get all payments
     */
    public function getAll(): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])->get();
    }

    /**
     * Get payment by ID
     */
    public function findById(int $id): ?Payment
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])->find($id);
    }

    /**
     * Get payment by payment number
     */
    public function findByPaymentNumber(string $paymentNumber): ?Payment
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('payment_number', $paymentNumber)
            ->first();
    }

    /**
     * Create a new payment
     */
    public function create(array $data): Payment
    {
        return $this->model->create($data);
    }

    /**
     * Update payment
     */
    public function update(int $id, array $data): ?Payment
    {
        $payment = $this->findById($id);
        
        if ($payment) {
            $payment->update($data);
            return $payment->fresh(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity']);
        }

        return null;
    }

    /**
     * Delete payment
     */
    public function delete(int $id): bool
    {
        $payment = $this->findById($id);
        
        if ($payment) {
            return $payment->delete();
        }

        return false;
    }

    /**
     * Get payments by invoice ID
     */
    public function getByInvoice(int $invoiceId): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get payments by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get payments by method
     */
    public function getByMethod(string $method): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('method', $method)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get completed payments
     */
    public function getCompleted(): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('status', 'completed')
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Get pending payments
     */
    public function getPending(): Collection
    {
        return $this->model->with(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

