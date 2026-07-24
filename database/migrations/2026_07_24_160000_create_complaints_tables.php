<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 20)->unique();

            // Registration
            $table->date('complaint_date');
            $table->time('complaint_time');
            $table->enum('receiving_channel', [
                'phone',
                'email',
                'walk_in',
                'social_media',
                'website',
                'mobile_app',
                'call_center',
                'other',
            ]);
            $table->enum('complaint_type', ['food_safety', 'non_food_safety']);
            $table->enum('status', [
                'open',
                'in_investigation',
                'pending_action',
                'customer_notified',
                'closed',
            ])->default('open');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->nullable();

            // Immutable after create
            $table->text('description');

            // Customer / order / product
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('department')->nullable();

            // Food safety fields
            $table->json('food_safety_indicators')->nullable();
            $table->enum('product_retention_status', [
                'secured',
                'disposed',
                'partial',
                'unknown',
            ])->nullable();
            $table->dateTime('qa_notified_at')->nullable();
            $table->string('qa_notify_method')->nullable();
            $table->string('qa_contact_name')->nullable();
            $table->boolean('qa_signed_off')->default(false);
            $table->foreignId('qa_signed_off_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('qa_signed_off_at')->nullable();

            // Non-food safety fields
            $table->enum('non_food_category', [
                'packaging',
                'labelling',
                'delivery',
                'service',
                'marketing',
                'other',
            ])->nullable();
            $table->string('forwarded_department')->nullable();
            $table->string('responsible_person_name')->nullable();
            $table->date('expected_response_date')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('non_food_corrective_action')->nullable();

            // Investigation & CAPA
            $table->text('investigation_findings')->nullable();
            $table->text('immediate_action')->nullable();
            $table->date('immediate_action_target_date')->nullable();
            $table->date('immediate_action_completed_at')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('corrective_action_target_date')->nullable();
            $table->date('corrective_action_completed_at')->nullable();
            $table->text('preventive_action')->nullable();
            $table->date('preventive_action_target_date')->nullable();
            $table->date('preventive_action_completed_at')->nullable();

            // Containment
            $table->boolean('consumer_health_risk')->default(false);
            $table->enum('containment_action', [
                'hold',
                'recall',
                'regulatory_notification',
            ])->nullable();
            $table->text('containment_notes')->nullable();

            // Ownership / closure / retention
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->date('retention_until');
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'complaint_type']);
            $table->index(['complaint_date']);
            $table->index(['product_id', 'batch_number']);
            $table->index(['department']);
            $table->index(['receiving_channel']);
        });

        Schema::create('complaint_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('complaint_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->enum('channel', ['email', 'phone', 'sms', 'in_app', 'other'])->default('email');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_authorized')->default(true);
            $table->boolean('is_unauthorized_flagged')->default(false);
            $table->string('recipient')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('attachment_type')->nullable(); // photo, lab_result, sample_reference, other
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('complaint_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_audits');
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaint_communications');
        Schema::dropIfExists('complaint_status_histories');
        Schema::dropIfExists('complaints');
    }
};
