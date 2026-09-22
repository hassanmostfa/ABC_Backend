<?php

namespace App\Http\Controllers\Api\Mobile\services;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ServicePurchaseRequest;
use App\Http\Resources\Mobile\ServiceCheckoutResource;
use App\Http\Resources\Mobile\ServiceResource;
use App\Http\Resources\Mobile\ServiceVoucherResource;
use App\Models\Customer;
use App\Repositories\Services\ServiceRepositoryInterface;
use App\Services\Services\ServicePurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends BaseApiController
{
    public function __construct(
        protected ServiceRepositoryInterface $serviceRepository,
        protected ServicePurchaseService $purchaseService
    ) {
    }

    /**
     * Active services for the mobile app, without pagination.
     */
    public function index(): JsonResponse
    {
        $services = $this->serviceRepository->getActive();

        return $this->successResponse(
            ServiceResource::collection($services),
            'Services retrieved successfully'
        );
    }

    /**
     * Buy a service with wallet or an online payment link.
     */
    public function purchase(ServicePurchaseRequest $request): JsonResponse
    {
        try {
            $customer = $this->resolveAuthenticatedCustomer();

            if (!$customer) {
                return $this->unauthorizedResponse(
                    'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
                );
            }

            $result = $this->purchaseService->purchase($customer, $request->validated());

            if (empty($result['is_checkout'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Service purchased successfully.',
                    'data' => new ServiceVoucherResource($result['voucher']),
                    'payment_link' => null,
                    'is_checkout' => false,
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => 'Complete payment to receive your voucher.',
                'data' => new ServiceCheckoutResource($result['checkout']),
                'payment_link' => $result['payment_link'],
                'is_checkout' => true,
            ], 201);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($e->getMessage(), $code);
            }

            return $this->serverErrorResponse('Failed to purchase service: ' . $e->getMessage());
        }
    }

    /**
     * Vouchers issued to the authenticated customer.
     */
    public function vouchers(Request $request): JsonResponse
    {
        $customer = $this->resolveAuthenticatedCustomer();

        if (!$customer) {
            return $this->unauthorizedResponse(
                'No authenticated customer found. This endpoint requires a customer token, not an admin token.'
            );
        }

        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $vouchers = $this->purchaseService->vouchersForCustomer($customer->id, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Vouchers retrieved successfully',
            'data' => ServiceVoucherResource::collection($vouchers->items()),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
            ],
        ]);
    }

    private function resolveAuthenticatedCustomer(): ?Customer
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof Customer ? $user : null;
    }
}
