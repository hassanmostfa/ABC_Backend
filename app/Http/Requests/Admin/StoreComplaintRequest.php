<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintPaymentMethod;
use App\Enums\ComplaintReceivingChannel;
use App\Enums\ComplaintSeverity;
use App\Enums\ComplaintType;
use App\Enums\ContainmentAction;
use App\Enums\FoodSafetyIndicator;
use App\Enums\NonFoodCategory;
use App\Enums\ProductRetentionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('complaint_type');

        $rules = [
            'receiving_channel' => ['required', Rule::enum(ComplaintReceivingChannel::class)],
            'complaint_type' => ['required', Rule::enum(ComplaintType::class)],
            'description' => 'required|string|min:10',

            'against' => 'nullable|string',
            'complaint_date' => 'nullable|date_format:Y-m-d',
            'complaint_time' => 'nullable|date_format:H:i:s',
            'severity' => ['nullable', Rule::enum(ComplaintSeverity::class)],

            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string',

            'order_id' => 'nullable',
            'product_id' => 'nullable',
            'product_name' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|string|max:255',

            'payment_method' => ['nullable', Rule::enum(ComplaintPaymentMethod::class)],
            'total_value' => 'nullable|string|max:100',
            'delivered_by' => 'nullable|string|max:255',
            'system_user_id' => 'nullable|string|max:100',

            'investigation_findings' => 'nullable|string',
            'immediate_action' => 'nullable|string',
            'immediate_action_target_date' => 'nullable|date_format:Y-m-d',
            'corrective_action' => 'nullable|string',
            'corrective_action_target_date' => 'nullable|date_format:Y-m-d',
            'preventive_action' => 'nullable|string',
            'preventive_action_target_date' => 'nullable|date_format:Y-m-d',
            'consumer_health_risk' => 'nullable|boolean',
            'containment_action' => ['nullable', Rule::enum(ContainmentAction::class)],
            'containment_notes' => 'nullable|string',

            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
            'attachment_type' => 'nullable|string|max:100',
        ];

        if ($type === ComplaintType::FoodSafety->value) {
            $rules = array_merge($rules, [
                'batch_number' => 'required|string|max:100',
                'product_id' => 'required_without:product_name|nullable',
                'product_name' => 'required_without:product_id|nullable|string|max:255',
                'qa_notified_at' => 'required|date',
                'qa_notify_method' => 'required|string|max:255',
                'qa_contact_name' => 'required|string|max:255',
                'food_safety_indicators' => 'required|array|min:1',
                'food_safety_indicators.*' => [Rule::enum(FoodSafetyIndicator::class)],
                'product_retention_status' => ['required', Rule::enum(ProductRetentionStatus::class)],
            ]);
        }

        if ($type === ComplaintType::NonFoodSafety->value) {
            $rules = array_merge($rules, [
                'non_food_category' => 'required|array|min:1',
                'non_food_category.*' => [Rule::enum(NonFoodCategory::class)],
                'forwarded_department' => 'nullable|string|max:255',
                'responsible_person_name' => 'nullable|string|max:255',
                'expected_response_date' => 'nullable|date_format:Y-m-d',
                'root_cause' => 'nullable|string',
                'non_food_corrective_action' => 'nullable|string',
            ]);
        }

        if ($this->boolean('consumer_health_risk')) {
            $rules['containment_action'] = ['required', Rule::enum(ContainmentAction::class)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Complaint description is mandatory and cannot be empty.',
            'batch_number.required' => 'Batch number is required for food safety complaints.',
            'qa_notified_at.required' => 'QA notification date/time is required for food safety complaints.',
            'food_safety_indicators.required' => 'At least one food safety indicator must be selected.',
            'non_food_category.required' => 'Select at least one non-food category.',
            'containment_action.required' => 'Containment action is required when consumer health risk is YES.',
        ];
    }
}
