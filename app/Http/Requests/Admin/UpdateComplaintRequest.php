<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintPaymentMethod;
use App\Enums\ComplaintReceivingChannel;
use App\Enums\ComplaintSeverity;
use App\Enums\ContainmentAction;
use App\Enums\FoodSafetyIndicator;
use App\Enums\NonFoodCategory;
use App\Enums\ProductRetentionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // description / reference_number / complaint_type intentionally omitted — immutable
            'against' => 'sometimes|nullable|string',
            'severity' => ['sometimes', Rule::enum(ComplaintSeverity::class)],
            'customer_id' => 'sometimes|nullable|integer|exists:customers,id',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'customer_email' => 'sometimes|nullable|email|max:255',
            'customer_phone' => 'sometimes|nullable|string|max:50',
            'customer_address' => 'sometimes|nullable|string',
            'order_id' => 'sometimes|nullable',
            'product_id' => 'sometimes|nullable',
            'product_name' => 'sometimes|nullable|string|max:255',
            'batch_number' => 'sometimes|nullable|string|max:100',
            'department' => 'sometimes|nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|string|max:255',
            'receiving_channel' => ['sometimes', Rule::enum(ComplaintReceivingChannel::class)],
            'complaint_date' => 'sometimes|nullable|date_format:Y-m-d',
            'complaint_time' => 'sometimes|nullable|date_format:H:i:s',

            'payment_method' => ['sometimes', 'nullable', Rule::enum(ComplaintPaymentMethod::class)],
            'total_value' => 'sometimes|nullable|string|max:100',
            'delivered_by' => 'sometimes|nullable|string|max:255',
            'system_user_id' => 'sometimes|nullable|string|max:100',

            'food_safety_indicators' => 'sometimes|nullable|array',
            'food_safety_indicators.*' => [Rule::enum(FoodSafetyIndicator::class)],
            'product_retention_status' => ['sometimes', 'nullable', Rule::enum(ProductRetentionStatus::class)],
            'qa_notified_at' => 'sometimes|nullable|date',
            'qa_notify_method' => 'sometimes|nullable|string|max:255',
            'qa_contact_name' => 'sometimes|nullable|string|max:255',

            'non_food_category' => 'sometimes|nullable|array|min:1',
            'non_food_category.*' => [Rule::enum(NonFoodCategory::class)],
            'forwarded_department' => 'sometimes|nullable|string|max:255',
            'responsible_person_name' => 'sometimes|nullable|string|max:255',
            'expected_response_date' => 'sometimes|nullable|date_format:Y-m-d',
            'root_cause' => 'sometimes|nullable|string',
            'non_food_corrective_action' => 'sometimes|nullable|string',

            'investigation_findings' => 'sometimes|nullable|string',
            'immediate_action' => 'sometimes|nullable|string',
            'immediate_action_target_date' => 'sometimes|nullable|date_format:Y-m-d',
            'immediate_action_completed_at' => 'sometimes|nullable|date_format:Y-m-d',
            'corrective_action' => 'sometimes|nullable|string',
            'corrective_action_target_date' => 'sometimes|nullable|date_format:Y-m-d',
            'corrective_action_completed_at' => 'sometimes|nullable|date_format:Y-m-d',
            'preventive_action' => 'sometimes|nullable|string',
            'preventive_action_target_date' => 'sometimes|nullable|date_format:Y-m-d',
            'preventive_action_completed_at' => 'sometimes|nullable|date_format:Y-m-d',

            'consumer_health_risk' => 'sometimes|boolean',
            'containment_action' => ['sometimes', 'nullable', Rule::enum(ContainmentAction::class)],
            'containment_notes' => 'sometimes|nullable|string',
        ];
    }
}
