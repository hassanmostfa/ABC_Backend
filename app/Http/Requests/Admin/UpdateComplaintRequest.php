<?php

namespace App\Http\Requests\Admin;

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
            // description intentionally omitted — immutable
            'severity' => ['sometimes', Rule::enum(ComplaintSeverity::class)],
            'customer_id' => 'sometimes|nullable|exists:customers,id',
            'customer_name' => 'sometimes|nullable|string|max:255',
            'customer_email' => 'sometimes|nullable|email|max:255',
            'customer_phone' => 'sometimes|nullable|string|max:50',
            'order_id' => 'sometimes|nullable|exists:orders,id',
            'product_id' => 'sometimes|nullable|exists:products,id',
            'product_name' => 'sometimes|nullable|string|max:255',
            'batch_number' => 'sometimes|nullable|string|max:100',
            'department' => 'sometimes|nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|exists:admins,id',
            'receiving_channel' => ['sometimes', Rule::enum(ComplaintReceivingChannel::class)],
            'complaint_date' => 'sometimes|date',
            'complaint_time' => 'sometimes|date_format:H:i:s',

            'food_safety_indicators' => 'sometimes|nullable|array',
            'food_safety_indicators.*' => [Rule::enum(FoodSafetyIndicator::class)],
            'product_retention_status' => ['sometimes', 'nullable', Rule::enum(ProductRetentionStatus::class)],
            'qa_notified_at' => 'sometimes|nullable|date',
            'qa_notify_method' => 'sometimes|nullable|string|max:100',
            'qa_contact_name' => 'sometimes|nullable|string|max:255',

            'non_food_category' => ['sometimes', 'nullable', Rule::enum(NonFoodCategory::class)],
            'forwarded_department' => 'sometimes|nullable|string|max:255',
            'responsible_person_name' => 'sometimes|nullable|string|max:255',
            'expected_response_date' => 'sometimes|nullable|date',
            'root_cause' => 'sometimes|nullable|string',
            'non_food_corrective_action' => 'sometimes|nullable|string',

            'investigation_findings' => 'sometimes|nullable|string',
            'immediate_action' => 'sometimes|nullable|string',
            'immediate_action_target_date' => 'sometimes|nullable|date',
            'immediate_action_completed_at' => 'sometimes|nullable|date',
            'corrective_action' => 'sometimes|nullable|string',
            'corrective_action_target_date' => 'sometimes|nullable|date',
            'corrective_action_completed_at' => 'sometimes|nullable|date',
            'preventive_action' => 'sometimes|nullable|string',
            'preventive_action_target_date' => 'sometimes|nullable|date',
            'preventive_action_completed_at' => 'sometimes|nullable|date',

            'consumer_health_risk' => 'sometimes|boolean',
            'containment_action' => ['sometimes', 'nullable', Rule::enum(ContainmentAction::class)],
            'containment_notes' => 'sometimes|nullable|string',
        ];
    }
}
