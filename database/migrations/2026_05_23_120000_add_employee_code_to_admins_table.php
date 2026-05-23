<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('admins', 'employee_code')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('employee_code')->nullable()->after('name');
            });
        }

        $admins = DB::table('admins')
            ->where(function ($query) {
                $query->whereNull('employee_code')->orWhere('employee_code', '');
            })
            ->orderBy('id')
            ->get(['id']);

        foreach ($admins as $admin) {
            DB::table('admins')
                ->where('id', $admin->id)
                ->update([
                    'employee_code' => 'EMP-' . str_pad((string) $admin->id, 4, '0', STR_PAD_LEFT),
                ]);
        }

        DB::statement('ALTER TABLE admins MODIFY employee_code VARCHAR(255) NOT NULL');

        Schema::table('admins', function (Blueprint $table) {
            $table->unique('employee_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
};
