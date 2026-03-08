<?php

namespace App\Http\Requests\Mobile;

use App\Models\Setting;

class ChargeWalletRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'amount.required' => $this->msg('Amount is required.', 'المبلغ مطلوب.'),
            'amount.numeric' => $this->msg('Amount must be a number.', 'يجب أن يكون المبلغ رقماً.'),
            'amount.min' => $this->msg("Minimum charge amount is {$minAmount} KWD.", "الحد الأدنى للشحن هو {$minAmount} د.ك."),
        ];
    }
}
