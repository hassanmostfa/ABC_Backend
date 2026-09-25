<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('general_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('type')->default('general'); // general, offer
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('message_en');
            $table->text('message_ar');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('push_sent_count')->default(0);
            $table->unsignedInteger('push_failed_count')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('general_notification_id')
                ->nullable()
                ->after('data')
                ->constrained('general_notifications')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('general_notification_id');
        });

        Schema::dropIfExists('general_notifications');
    }
};
