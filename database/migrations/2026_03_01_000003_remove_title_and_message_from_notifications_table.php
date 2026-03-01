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
        $columnsToDrop = [
            'title',
            'message',
            'title_en',
            'title_ar',
            'message_en',
            'message_ar',
        ];

        $existingColumns = array_filter($columnsToDrop, function (string $column): bool {
            return Schema::hasColumn('notifications', $column);
        });

        if (!empty($existingColumns)) {
            Schema::table('notifications', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'title_en')) {
                $table->string('title_en')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'title_ar')) {
                $table->string('title_ar')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'message_en')) {
                $table->text('message_en')->nullable();
            }
            if (!Schema::hasColumn('notifications', 'message_ar')) {
                $table->text('message_ar')->nullable();
            }
        });
    }
};
