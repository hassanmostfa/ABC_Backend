<?php

namespace App\Http\Requests\Admin;

use App\Models\WalletChargeOffer;
use Illuminate\Foundation\Http\FormRequest;

class WalletChargeOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $offerId = $this->route('id');
        $isUpdate = !is_null($offerId);
        $uniqueChargeAmount = 'unique:wallet_charge_offers,charge_amount';

        if ($isUpdate) {
            $uniqueChargeAmount .= ',' . $offerId;
        }

        return [
            'charge_amount' => ($isUpdate ? 'sometimes|' : '') . 'required|numeric|min:0.001|' . $uniqueChargeAmount,
            'get_amount' => ($isUpdate ? 'sometimes|' : '') . 'required|numeric|min:0.001',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $chargeAmount = $this->input('charge_amount');
            $getAmount = $this->input('get_amount');
            $offerId = $this->route('id');

            if (($chargeAmount === null || $getAmount === null) && $offerId) {
                $offer = WalletChargeOffer::query()->find($offerId);
                if ($offer) {
                    $chargeAmount = $chargeAmount ?? $offer->charge_amount;
                    $getAmount = $getAmount ?? $offer->get_amount;
                }
            }

            if ($chargeAmount !== null && $getAmount !== null && (float) $getAmount <= (float) $chargeAmount) {
                $validator->errors()->add('get_amount', 'Get amount must be greater than charge amount.');
            }
        });
    }
}
