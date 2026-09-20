<?php

namespace App\Http\Requests\Mobile;

use App\Models\Setting;
use Illuminate\Validation\Rule;

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
            'amount' => [
                'required_without:offer_id',
                'nullable',
                'numeric',
                Rule::when(!$this->filled('offer_id'), ['min:' . $minAmount]),
            ],
            'offer_id' => [
                'nullable',
                'integer',
                Rule::exists('wallet_charge_offers', 'id')->where('is_active', true),
            ],
            'src' => 'required|string|in:knet,cc',
        ];
    }

    public function messages(): array
    {
        $minAmount = (float) Setting::getValue('minimum_wallet_charge', 1);

        return [
            'amount.required' => $this->msg('Amount is required.', 'المبلغ مطلوب.'),
            'amount.required_without' => $this->msg('Amount is required when no offer is selected.', 'المبلغ مطلوب عند عدم اختيار عرض.'),
            'amount.numeric' => $this->msg('Amount must be a number.', 'يجب أن يكون المبلغ رقماً.'),
            'amount.min' => $this->msg("Minimum charge amount is {$minAmount} KWD.", "الحد الأدنى للشحن هو {$minAmount} د.ك."),
            'offer_id.exists' => $this->msg('Selected charge offer is not available.', 'عرض الشحن المحدد غير متاح.'),
            'src.required' => $this->msg('Payment source is required.', 'مصدر الدفع مطلوب.'),
            'src.in' => $this->msg('Payment source must be knet or cc.', 'مصدر الدفع يجب أن يكون knet أو cc.'),
        ];
    }
}
