<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Admin\PointsTransactionResource;
use App\Models\PointsTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PointsTransactionController extends BaseApiController
{
    /**
     * Display a listing of points transactions with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'type' => 'nullable|in:points_to_wallet,points_earned',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PointsTransaction::query()
            ->with('customer')
            ->orderBy('created_at', 'desc');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->input('per_page', 15);
        $transactions = $query->paginate($perPage);

        $response = [
            'success' => true,
            'message' => 'Points transactions retrieved successfully',
            'data' => PointsTransactionResource::collection($transactions->items()),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ];

        $filters = [
            'customer_id' => $request->input('customer_id'),
            'type' => $request->input('type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }
}
