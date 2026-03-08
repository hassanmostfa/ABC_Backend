<?php

namespace App\Http\Requests\Mobile;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class ConvertPointsToWalletRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = Auth::guard('sanctum')->user();
        $maxPoints = $customer ? ($customer->points ?? 0) : 0;
        $minPoints = (int) Setting::getValue('min_points_to_convert', 10);
        $request = $this;

        return [
            'points' => [
                'required',
                'integer',
                'min:' . $minPoints,
                function ($attribute, $value, $fail) use ($maxPoints, $request) {
                    if ($maxPoints > 0 && $value > $maxPoints) {
                        $fail($request->msg('You do not have enough points. Available: ' . $maxPoints, 'النقاط غير كافية. المتاح: ' . $maxPoints));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        $minPoints = (int) Setting::getValue('min_points_to_convert', 10);

        return [
            'points.required' => $this->msg('Points amount is required.', 'مبلغ النقاط مطلوب.'),
            'points.integer' => $this->msg('Points must be a whole number.', 'النقاط يجب أن تكون رقماً صحيحاً.'),
            'points.min' => $this->msg("Minimum points to convert is {$minPoints}.", "الحد الأدنى لتحويل النقاط هو {$minPoints}."),
        ];
    }
}
