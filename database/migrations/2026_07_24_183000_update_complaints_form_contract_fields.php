<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('complaints', 'against')) {
                $table->text('against')->nullable()->after('description');
            }
            if (!Schema::hasColumn('complaints', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('department');
            }
            if (!Schema::hasColumn('complaints', 'total_value')) {
                $table->string('total_value', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('complaints', 'delivered_by')) {
                $table->string('delivered_by')->nullable()->after('total_value');
            }
            if (!Schema::hasColumn('complaints', 'system_user_id')) {
                $table->string('system_user_id', 100)->nullable()->after('delivered_by');
            }
            if (!Schema::hasColumn('complaints', 'qa_signoff_notes')) {
                $table->text('qa_signoff_notes')->nullable()->after('qa_signed_off_at');
            }
        });

        // assigned_to: drop FK if present, recreate as string
        $this->convertAssignedToString();
        $this->convertNonFoodCategoryToJson();
    }

    public function down(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            foreach ([
                'customer_address',
                'against',
                'payment_method',
                'total_value',
                'delivered_by',
                'system_user_id',
                'qa_signoff_notes',
            ] as $column) {
                if (Schema::hasColumn('complaints', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function convertAssignedToString(): void
    {
        if (!Schema::hasColumn('complaints', 'assigned_to')) {
            return;
        }

        try {
            Schema::table('complaints', function (Blueprint $table) {
                $table->dropForeign(['assigned_to']);
            });
        } catch (\Throwable $e) {
            // ignore if FK does not exist
        }

        $database = DB::getDatabaseName();
        $type = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', 'complaints')
            ->where('column_name', 'assigned_to')
            ->value('DATA_TYPE');

        if (in_array(strtolower((string) $type), ['varchar', 'text'], true)) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('assigned_to_tmp')->nullable()->after('created_by');
        });

        DB::statement('UPDATE complaints SET assigned_to_tmp = CAST(assigned_to AS CHAR) WHERE assigned_to IS NOT NULL');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('assigned_to');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('assigned_to')->nullable()->after('created_by');
        });

        DB::statement('UPDATE complaints SET assigned_to = assigned_to_tmp');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('assigned_to_tmp');
        });
    }

    protected function convertNonFoodCategoryToJson(): void
    {
        if (!Schema::hasColumn('complaints', 'non_food_category')) {
            return;
        }

        $database = DB::getDatabaseName();
        $type = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', 'complaints')
            ->where('column_name', 'non_food_category')
            ->value('DATA_TYPE');

        if (strtolower((string) $type) === 'json') {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->json('non_food_category_tmp')->nullable()->after('qa_signoff_notes');
        });

        $rows = DB::table('complaints')->select('id', 'non_food_category')->get();
        foreach ($rows as $row) {
            $value = $row->non_food_category;
            $json = null;
            if ($value !== null && $value !== '') {
                $decoded = json_decode((string) $value, true);
                $json = is_array($decoded) ? json_encode(array_values($decoded)) : json_encode([(string) $value]);
            }
            DB::table('complaints')->where('id', $row->id)->update([
                'non_food_category_tmp' => $json,
            ]);
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('non_food_category');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->json('non_food_category')->nullable()->after('qa_signoff_notes');
        });

        DB::statement('UPDATE complaints SET non_food_category = non_food_category_tmp');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('non_food_category_tmp');
        });
    }
};
