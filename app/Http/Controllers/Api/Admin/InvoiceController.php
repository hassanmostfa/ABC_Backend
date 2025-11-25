<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Repositories\InvoiceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceController extends BaseApiController
{
    protected $invoiceRepository;

    public function __construct(InvoiceRepositoryInterface $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,paid,cancelled,refunded',
            'order_id' => 'nullable|integer|exists:orders,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'order_id' => $request->input('order_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $invoices = $this->invoiceRepository->getAllPaginated($filters, $perPage);

        // Transform invoices using resource
        $transformedInvoices = InvoiceResource::collection($invoices->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Invoices retrieved successfully',
            'data' => $transformedInvoices,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Display the specified invoice.
     */
    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoiceRepository->findById($id);

        if (!$invoice) {
            return $this->notFoundResponse('Invoice not found');
        }

        // Load all relationships
        $invoice->load([
            'order.customer',
            'order.charity',
            'order.offer',
            'order.items.product',
            'order.items.variant',
            'order.invoice',
            'order.delivery',
            'payments'
        ]);

        return $this->resourceResponse(new InvoiceResource($invoice), 'Invoice retrieved successfully');
    }

    /**
     * Update the specified invoice status.
     */
    public function update(UpdateInvoiceRequest $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceRepository->findById($id);

        if (!$invoice) {
            return $this->notFoundResponse('Invoice not found');
        }

        try {
            $updateData = $request->validated();
            
            // If status is being updated to 'paid', set paid_at timestamp
            if (isset($updateData['status']) && $updateData['status'] === 'paid' && $invoice->status !== 'paid') {
                $updateData['paid_at'] = now();
            } elseif (isset($updateData['status']) && $updateData['status'] !== 'paid' && $invoice->status === 'paid') {
                // If status is changed from 'paid' to something else, clear paid_at
                $updateData['paid_at'] = null;
            }

            $invoice = $this->invoiceRepository->update($id, $updateData);

            if (!$invoice) {
                return $this->errorResponse('Failed to update invoice', 500);
            }

            // Reload with relationships
            $invoice = $this->invoiceRepository->findById($id);
            $invoice->load([
                'order.customer',
                'order.charity',
                'order.offer',
                'order.items.product',
                'order.items.variant',
                'order.invoice',
                'order.delivery',
                'payments'
            ]);

            return $this->updatedResponse(new InvoiceResource($invoice), 'Invoice updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update invoice: ' . $e->getMessage(), 500);
        }
    }
}

