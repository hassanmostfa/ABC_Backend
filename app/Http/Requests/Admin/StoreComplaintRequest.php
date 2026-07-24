<?php

namespace App\Http\Requests\Admin;

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
            'complaint_date' => 'nullable|date',
            'complaint_time' => 'nullable|date_format:H:i:s',
            'receiving_channel' => ['required', Rule::enum(ComplaintReceivingChannel::class)],
            'complaint_type' => ['required', Rule::enum(ComplaintType::class)],
            'severity' => ['nullable', Rule::enum(ComplaintSeverity::class)],
            'description' => 'required|string|min:10',

            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'order_id' => 'nullable|exists:orders,id',
            'product_id' => 'nullable|exists:products,id',
            'product_name' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:admins,id',

            'investigation_findings' => 'nullable|string',
            'immediate_action' => 'nullable|string',
            'immediate_action_target_date' => 'nullable|date',
            'corrective_action' => 'nullable|string',
            'corrective_action_target_date' => 'nullable|date',
            'preventive_action' => 'nullable|string',
            'preventive_action_target_date' => 'nullable|date',
            'consumer_health_risk' => 'nullable|boolean',
            'containment_action' => ['nullable', Rule::enum(ContainmentAction::class)],
            'containment_notes' => 'nullable|string',

            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
            'attachment_type' => 'nullable|string|max:50',
        ];

        if ($type === ComplaintType::FoodSafety->value) {
            $rules = array_merge($rules, [
                'product_name' => 'required_without:product_id|nullable|string|max:255',
                'product_id' => 'required_without:product_name|nullable|exists:products,id',
                'batch_number' => 'required|string|max:100',
                'qa_notified_at' => 'required|date',
                'qa_notify_method' => 'required|string|max:100',
                'qa_contact_name' => 'required|string|max:255',
                'food_safety_indicators' => 'required|array|min:1',
                'food_safety_indicators.*' => [Rule::enum(FoodSafetyIndicator::class)],
                'product_retention_status' => ['required', Rule::enum(ProductRetentionStatus::class)],
            ]);
        }

        if ($type === ComplaintType::NonFoodSafety->value) {
            $rules = array_merge($rules, [
                'non_food_category' => ['required', Rule::enum(NonFoodCategory::class)],
                'forwarded_department' => 'nullable|string|max:255',
                'responsible_person_name' => 'nullable|string|max:255',
                'expected_response_date' => 'nullable|date|after_or_equal:today',
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
            'containment_action.required' => 'Containment action is required when consumer health risk is YES.',
        ];
    }
}
