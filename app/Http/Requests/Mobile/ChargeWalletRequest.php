<?php

namespace App\Http\Requests\Mobile;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class ChargeWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    public function rules(): array
    {
        $minAmount = (float) Setting::getValue('minimum_wallet_charge', 1);

        return [
            'amount' => ['required', 'numeric', 'min:' . $minAmount],
        ];
    }

    public function messages(): array
    {
        $minAmount = (float) Setting::getValue('minimum_wallet_charge', 1);

        return [
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => "Minimum charge amount is {$minAmount} KWD.",
        ];
    }
}
