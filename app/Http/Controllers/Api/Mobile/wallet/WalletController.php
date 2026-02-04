<?php

namespace App\Http\Controllers\Api\Mobile\wallet;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ChargeWalletRequest;
use App\Http\Resources\Mobile\WalletChargeResource;
use App\Models\Setting;
use App\Services\WalletChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WalletController extends BaseApiController
{
    public function __construct(
        protected WalletChargeService $walletChargeService
    ) {}

    /**
     * Get wallet charge settings (wallet_charge_gift, minimum_wallet_charge).
     * Public so the app can show bonus and minimum before charge.
     */
    public function chargeSettings(): JsonResponse
    {
        $walletChargeGift = (float) Setting::getValue('wallet_charge_gift');
        $minimumWalletCharge = (float) Setting::getValue('minimum_wallet_charge');

        return $this->successResponse([
            'wallet_charge_gift' => $walletChargeGift,
            'minimum_wallet_charge' => $minimumWalletCharge,
        ], 'Wallet charge settings retrieved successfully');
    }

    /**
     * Create wallet charge and get payment link
     */
    public function charge(ChargeWalletRequest $request): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();

        if (!$customer) {
            return $this->unauthorizedResponse('No authenticated customer found');
        }

        $amount = (float) $request->validated('amount');
        $result = $this->walletChargeService->createCharge($customer->id, $amount);

        if (!$result['success']) {
            return $this->errorResponse($result['message'] ?? 'Failed to create charge', 400);
        }

        return $this->successResponse([
            'payment_link' => $result['payment_link'],
            'payment' => new WalletChargeResource($result['payment']),
            'amount' => $result['amount'],
            'bonus_amount' => $result['bonus_amount'],
            'total_amount' => $result['total_amount'],
        ], 'Payment link generated successfully');
    }
}
