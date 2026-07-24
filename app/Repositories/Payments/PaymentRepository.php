<?php

namespace App\Repositories\Payments;

use App\Models\Payment;
use App\Support\KuwaitPhone;
use Illuminate\Database\Eloquent\Builder;
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
     * @return list<string>
     */
    protected function paymentRelations(): array
    {
        return [
            'invoice',
            'invoice.order',
            'invoice.order.customer',
            'invoice.order.charity',
            'invoice.order.items',
            'invoice.order.customerAddress',
            'customer',
            'creator',
            'orderCheckout',
            'orderCheckout.customer',
            'orderCheckout.order',
            'orderCheckout.order.customer',
            'orderCheckout.order.charity',
            'orderCheckout.order.items',
            'orderCheckout.order.customerAddress',
            'orderCheckout.order.invoice',
        ];
    }

    /**
     * Get all payments with pagination, search and filters
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with($this->paymentRelations());

        // Search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $this->applyPaymentSearch($query, (string) $filters['search']);
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

        // Filter by checkout source (app / web / call_center); omit to return all
        if (isset($filters['source']) && !empty(trim($filters['source']))) {
            $source = $this->normalizePaymentSource((string) $filters['source']);
            if ($source !== null) {
                $query->whereHas('orderCheckout', function (Builder $checkoutQuery) use ($source) {
                    $checkoutQuery->where('source', $source);
                });
            }
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
     * Normalize API source aliases to stored checkout source values.
     */
    protected function normalizePaymentSource(string $source): ?string
    {
        $source = strtolower(trim($source));

        return match ($source) {
            'app' => 'app',
            'web', 'website' => 'web',
            'call_center', 'calls', 'cals' => 'call_center',
            default => null,
        };
    }

    protected function applyPaymentSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $phoneTerms = $this->phoneSearchTerms($search);

        $query->where(function (Builder $q) use ($search, $phoneTerms) {
            $q->where('payment_number', 'LIKE', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search, $phoneTerms) {
                    $this->applyCustomerNameOrPhoneSearch($customerQuery, $search, $phoneTerms);
                })
                ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search, $phoneTerms) {
                    $invoiceQuery->where('invoice_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('order', function (Builder $orderQuery) use ($search, $phoneTerms) {
                            $orderQuery->where('order_number', 'LIKE', "%{$search}%")
                                ->orWhereHas('customer', function (Builder $customerQuery) use ($search, $phoneTerms) {
                                    $this->applyCustomerNameOrPhoneSearch($customerQuery, $search, $phoneTerms);
                                });
                        });
                })
                ->orWhereHas('orderCheckout', function (Builder $checkoutQuery) use ($search, $phoneTerms) {
                    $checkoutQuery->where('order_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search, $phoneTerms) {
                            $this->applyCustomerNameOrPhoneSearch($customerQuery, $search, $phoneTerms);
                        })
                        ->orWhereHas('order', function (Builder $orderQuery) use ($search, $phoneTerms) {
                            $orderQuery->where('order_number', 'LIKE', "%{$search}%")
                                ->orWhereHas('customer', function (Builder $customerQuery) use ($search, $phoneTerms) {
                                    $this->applyCustomerNameOrPhoneSearch($customerQuery, $search, $phoneTerms);
                                });
                        });
                });
        });
    }

    /**
     * @param  list<string>  $phoneTerms
     */
    protected function applyCustomerNameOrPhoneSearch(Builder $query, string $search, array $phoneTerms): void
    {
        $query->where(function (Builder $q) use ($search, $phoneTerms) {
            $q->where('name', 'LIKE', "%{$search}%");

            foreach ($phoneTerms as $term) {
                $q->orWhere('phone', 'LIKE', '%' . $term . '%');
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function phoneSearchTerms(string $search): array
    {
        $terms = [trim($search)];
        $digitsOnly = preg_replace('/\D+/', '', $search) ?? '';

        if ($digitsOnly !== '') {
            $terms[] = $digitsOnly;

            $localDigits = KuwaitPhone::withoutCountryCode($digitsOnly);
            if ($localDigits !== '') {
                $terms[] = $localDigits;
                $terms[] = '965' . $localDigits;
                $terms[] = '+965' . $localDigits;
            }
        }

        return array_values(array_unique(array_filter($terms, static fn ($term) => $term !== '')));
    }

    /**
     * Get all payments
     */
    public function getAll(): Collection
    {
        return $this->model->with($this->paymentRelations())->get();
    }

    /**
     * Get payment by ID
     */
    public function findById(int $id): ?Payment
    {
        return $this->model->with($this->paymentRelations())->find($id);
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
            return $payment->fresh(['invoice', 'invoice.order', 'invoice.order.customer', 'invoice.order.charity', 'customer']);
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

