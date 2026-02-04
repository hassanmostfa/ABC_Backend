<?php

namespace App\Http\Controllers\Api\Mobile\points_transactions;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ConvertPointsToWalletRequest;
use App\Http\Resources\Mobile\CustomerResource;
use App\Http\Resources\Mobile\PointsTransactionResource;
use App\Models\Setting;
use App\Repositories\CustomerRepositoryInterface;
use App\Services\PointsTransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PointsTransactionController extends BaseApiController
{
    public function __construct(
        protected PointsTransactionService $pointsTransactionService,
        protected CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * Get points convert settings (one_point_money_value, min_points_to_convert).
     * Public so the app can show rate and minimum before convert.
     */
    public function convertSettings(): JsonResponse
    {
        $onePointMoneyValue = (float) Setting::getValue('one_point_money_value', Setting::getValue('one_point_dicount', 0.1));
        $minPointsToConvert = (int) Setting::getValue('min_points_to_convert', 10);

        return $this->successResponse([
            'one_point_money_value' => $onePointMoneyValue,
            'min_points_to_convert' => $minPointsToConvert,
        ], 'Points convert settings retrieved successfully');
    }

    /**
     * Convert points to wallet balance
     */
    public function convertPoints(ConvertPointsToWalletRequest $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $points = (int) $request->validated('points');
        $result = $this->pointsTransactionService->convertPointsToWallet($customer->id, $points);

        if (!$result['success']) {
            return $this->errorResponse($result['message'] ?? 'Conversion failed', 400);
        }

        // Reload customer with wallet for updated balance and points
        $updatedCustomer = $this->customerRepository->findById($customer->id);

        return $this->successResponse([
            'customer' => new CustomerResource($updatedCustomer),
            'conversion' => [
                'points_converted' => $result['points'],
                'amount_added' => $result['amount'],
            ],
        ], $result['message']);
    }

    /**
     * Get points transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $request->validate([
            'type' => 'nullable|in:points_to_wallet,points_earned',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $filters = [
            'type' => $request->input('type'),
        ];
        $filters = array_filter($filters);
        $perPage = $request->input('per_page', 15);

        $transactions = $this->pointsTransactionService->getCustomerTransactions(
            $customer->id,
            $filters,
            $perPage
        );

        $transactions->through(fn ($t) => new PointsTransactionResource($t));

        return $this->paginatedResponse($transactions, 'Transactions retrieved successfully');
    }
}
