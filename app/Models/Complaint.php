<?php

namespace App\Models;

use App\Enums\ComplaintReceivingChannel;
use App\Enums\ComplaintSeverity;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\ContainmentAction;
use App\Enums\NonFoodCategory;
use App\Enums\ProductRetentionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    public static string $STORAGE_DIR = 'files/complaints';

    protected $fillable = [
        'reference_number',
        'complaint_date',
        'complaint_time',
        'receiving_channel',
        'complaint_type',
        'status',
        'severity',
        'description',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'order_id',
        'product_id',
        'product_name',
        'batch_number',
        'department',
        'food_safety_indicators',
        'product_retention_status',
        'qa_notified_at',
        'qa_notify_method',
        'qa_contact_name',
        'qa_signed_off',
        'qa_signed_off_by',
        'qa_signed_off_at',
        'non_food_category',
        'forwarded_department',
        'responsible_person_name',
        'expected_response_date',
        'root_cause',
        'non_food_corrective_action',
        'investigation_findings',
        'immediate_action',
        'immediate_action_target_date',
        'immediate_action_completed_at',
        'corrective_action',
        'corrective_action_target_date',
        'corrective_action_completed_at',
        'preventive_action',
        'preventive_action_target_date',
        'preventive_action_completed_at',
        'consumer_health_risk',
        'containment_action',
        'containment_notes',
        'created_by',
        'assigned_to',
        'closed_by',
        'closed_at',
        'retention_until',
        'reminder_sent_at',
    ];

    protected $casts = [
        'complaint_date' => 'date',
        'expected_response_date' => 'date',
        'immediate_action_target_date' => 'date',
        'immediate_action_completed_at' => 'date',
        'corrective_action_target_date' => 'date',
        'corrective_action_completed_at' => 'date',
        'preventive_action_target_date' => 'date',
        'preventive_action_completed_at' => 'date',
        'retention_until' => 'date',
        'food_safety_indicators' => 'array',
        'qa_signed_off' => 'boolean',
        'consumer_health_risk' => 'boolean',
        'qa_notified_at' => 'datetime',
        'qa_signed_off_at' => 'datetime',
        'closed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'status' => ComplaintStatus::class,
        'complaint_type' => ComplaintType::class,
        'receiving_channel' => ComplaintReceivingChannel::class,
        'severity' => ComplaintSeverity::class,
        'product_retention_status' => ProductRetentionStatus::class,
        'non_food_category' => NonFoodCategory::class,
        'containment_action' => ContainmentAction::class,
    ];

    public function isFoodSafety(): bool
    {
        return $this->complaint_type === ComplaintType::FoodSafety;
    }

    public function canTransitionTo(ComplaintStatus|string $status): bool
    {
        $target = $status instanceof ComplaintStatus
            ? $status
            : ComplaintStatus::from($status);

        return $this->status->canTransitionTo($target);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function closedBy()
    {
        return $this->belongsTo(Admin::class, 'closed_by');
    }

    public function qaSignedOffBy()
    {
        return $this->belongsTo(Admin::class, 'qa_signed_off_by');
    }

    public function statusHistories()
    {
        return $this->hasMany(ComplaintStatusHistory::class)->orderByDesc('id');
    }

    public function communications()
    {
        return $this->hasMany(ComplaintCommunication::class)->orderByDesc('id');
    }

    public function attachments()
    {
        return $this->hasMany(ComplaintAttachment::class)->orderByDesc('id');
    }

    public function audits()
    {
        return $this->hasMany(ComplaintAudit::class)->orderByDesc('id');
    }
}
