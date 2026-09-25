<?php

namespace App\Http\Requests\Admin;

use App\Models\GeneralNotification;
use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeneralNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(GeneralNotification::TYPES)],
            'offer_id' => [
                'required_if:type,' . GeneralNotification::TYPE_OFFER,
                'prohibited_unless:type,' . GeneralNotification::TYPE_OFFER,
                'nullable',
                'integer',
                'exists:offers,id',
            ],
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'message_en' => 'required|string|max:1000',
            'message_ar' => 'required|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('type') !== GeneralNotification::TYPE_OFFER) {
                return;
            }

            $offer = Offer::query()->find($this->input('offer_id'));

            if (!$offer || $offer->is_subscription) {
                $validator->errors()->add('offer_id', 'The selected offer is not available in the mobile app.');
                return;
            }

            if (!$offer->isActive()) {
                $validator->errors()->add('offer_id', 'The selected offer must be active and within its start and end dates.');
            }
        });
    }
}
