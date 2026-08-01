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

        // Production may still have FK constraints from the original create migration.
        $this->dropForeignKeysIfExist(['order_id', 'product_id']);

        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'order_id')) {
                $table->string('order_id', 100)->nullable()->change();
            }
            if (Schema::hasColumn('complaints', 'product_id')) {
                $table->string('product_id', 100)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        // Best-effort revert: cast numeric strings back to bigint, clear non-numeric.
        if (Schema::hasColumn('complaints', 'order_id')) {
            DB::table('complaints')
                ->whereNotNull('order_id')
                ->whereRaw("order_id NOT REGEXP '^[0-9]+$'")
                ->update(['order_id' => null]);
        }
        if (Schema::hasColumn('complaints', 'product_id')) {
            DB::table('complaints')
                ->whereNotNull('product_id')
                ->whereRaw("product_id NOT REGEXP '^[0-9]+$'")
                ->update(['product_id' => null]);
        }

        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
            }
            if (Schema::hasColumn('complaints', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignKeysIfExist(array $columns): void
    {
        $database = DB::getDatabaseName();

        foreach ($columns as $column) {
            $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->select('CONSTRAINT_NAME')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'complaints')
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('CONSTRAINT_NAME')
                ->unique()
                ->values();

            foreach ($constraints as $constraint) {
                Schema::table('complaints', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint);
                });
            }
        }
    }
};
