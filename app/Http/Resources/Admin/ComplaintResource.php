<?php

namespace App\Http\Resources\Admin;

use App\Enums\ComplaintType;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    use ManagesFileUploads;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'status' => $this->status?->value,
            'allowed_next_statuses' => $this->status?->nextStatusValues() ?? [],
            'complaint_type' => $this->complaint_type?->value,
            'receiving_channel' => $this->receiving_channel?->value,
            'severity' => $this->severity?->value,
            'description' => $this->description,
            'description_immutable' => true,
            'against' => $this->against,
            'complaint_date' => optional($this->complaint_date)->format('Y-m-d'),
            'complaint_time' => $this->formatTime($this->complaint_time),

            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,

            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'batch_number' => $this->batch_number,
            'department' => $this->department,
            'assigned_to' => $this->assigned_to,
            'payment_method' => $this->payment_method?->value,
            'total_value' => $this->total_value,
            'delivered_by' => $this->delivered_by,
            'system_user_id' => $this->system_user_id,

            // Who from call center / admin created this complaint
            'created_by' => $this->created_by,
            'created_by_admin' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email ?? null,
            ] : null),

            'food_safety' => $this->complaint_type === ComplaintType::FoodSafety ? [
                'batch_number' => $this->batch_number,
                'product_id' => $this->product_id,
                'product_name' => $this->product_name,
                'qa_notified_at' => \format_datetime_app_tz($this->qa_notified_at),
                'qa_notify_method' => $this->qa_notify_method,
                'qa_contact_name' => $this->qa_contact_name,
                'food_safety_indicators' => $this->food_safety_indicators ?? [],
                'product_retention_status' => $this->product_retention_status?->value,
                'qa_signed_off' => (bool) $this->qa_signed_off,
                'qa_signed_off_at' => \format_datetime_app_tz($this->qa_signed_off_at),
                'qa_signed_off_by' => $this->qa_signed_off_by,
                'qa_signoff_notes' => $this->qa_signoff_notes,
            ] : null,

            'non_food_safety' => $this->complaint_type === ComplaintType::NonFoodSafety ? [
                'non_food_category' => $this->non_food_category ?? [],
                'forwarded_department' => $this->forwarded_department,
                'responsible_person_name' => $this->responsible_person_name,
                'expected_response_date' => optional($this->expected_response_date)->format('Y-m-d'),
                'root_cause' => $this->root_cause,
                'non_food_corrective_action' => $this->non_food_corrective_action,
            ] : null,

            'investigation_capa' => [
                'investigation_findings' => $this->investigation_findings,
                'immediate_action' => $this->immediate_action,
                'immediate_action_target_date' => optional($this->immediate_action_target_date)->format('Y-m-d'),
                'immediate_action_completed_at' => optional($this->immediate_action_completed_at)->format('Y-m-d'),
                'corrective_action' => $this->corrective_action,
                'corrective_action_target_date' => optional($this->corrective_action_target_date)->format('Y-m-d'),
                'corrective_action_completed_at' => optional($this->corrective_action_completed_at)->format('Y-m-d'),
                'preventive_action' => $this->preventive_action,
                'preventive_action_target_date' => optional($this->preventive_action_target_date)->format('Y-m-d'),
                'preventive_action_completed_at' => optional($this->preventive_action_completed_at)->format('Y-m-d'),
                'consumer_health_risk' => (bool) $this->consumer_health_risk,
                'containment_action' => $this->containment_action?->value,
                'containment_notes' => $this->containment_notes,
            ],

            'closed_by' => $this->closed_by,
            'closed_at' => \format_datetime_app_tz($this->closed_at),
            'retention_until' => optional($this->retention_until)->format('Y-m-d'),
            'created_at' => \format_datetime_app_tz($this->created_at),
            'updated_at' => \format_datetime_app_tz($this->updated_at),

            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email ?? null,
            ] : null),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name_en ?? $this->product->name ?? null,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ] : null),
            'qa_signed_off_by_admin' => $this->whenLoaded('qaSignedOffBy', fn () => $this->qaSignedOffBy ? [
                'id' => $this->qaSignedOffBy->id,
                'name' => $this->qaSignedOffBy->name,
            ] : null),

            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'attachment_type' => $a->attachment_type,
                'notes' => $a->notes,
                'url' => $this->getFileUrl($a->file_path, 'public'),
                'uploaded_by' => $a->uploaded_by,
                'created_at' => \format_datetime_app_tz($a->created_at),
            ])),

            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'notes' => $h->notes,
                'changed_by' => $h->changed_by,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => \format_datetime_app_tz($h->created_at),
            ])),

            'communications' => $this->whenLoaded('communications', fn () => $this->communications->map(fn ($c) => [
                'id' => $c->id,
                'direction' => $c->direction,
                'channel' => $c->channel,
                'subject' => $c->subject,
                'body' => $c->body,
                'is_authorized' => (bool) $c->is_authorized,
                'is_unauthorized_flagged' => (bool) $c->is_unauthorized_flagged,
                'recipient' => $c->recipient,
                'sent_by' => $c->sent_by,
                'sent_by_name' => $c->sentBy?->name,
                'created_at' => \format_datetime_app_tz($c->created_at),
            ])),

            'audits' => $this->whenLoaded('audits', fn () => $this->audits->map(fn ($a) => [
                'id' => $a->id,
                'field_name' => $a->field_name,
                'old_value' => $a->old_value,
                'new_value' => $a->new_value,
                'changed_by' => $a->changed_by,
                'changed_by_name' => $a->changedBy?->name,
                'created_at' => \format_datetime_app_tz($a->created_at),
            ])),
        ];
    }

    protected function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        $value = (string) $time;

        return strlen($value) > 8 ? substr($value, 0, 8) : $value;
    }
}
