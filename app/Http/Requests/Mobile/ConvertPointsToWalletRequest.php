<?php

namespace App\Http\Requests\Mobile;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConvertPointsToWalletRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the response that should be returned if validation fails.
     */
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customer = Auth::guard('sanctum')->user();
        $maxPoints = $customer ? ($customer->points ?? 0) : 0;
        $minPoints = (int) Setting::getValue('min_points_to_convert', 10);

        return [
            'points' => [
                'required',
                'integer',
                'min:' . $minPoints,
                function ($attribute, $value, $fail) use ($maxPoints) {
                    if ($maxPoints > 0 && $value > $maxPoints) {
                        $fail('You do not have enough points. Available: ' . $maxPoints);
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $minPoints = (int) Setting::getValue('min_points_to_convert', 10);

        return [
            'points.required' => 'Points amount is required.',
            'points.integer' => 'Points must be a whole number.',
            'points.min' => "Minimum points to convert is {$minPoints}.",
        ];
    }
}
