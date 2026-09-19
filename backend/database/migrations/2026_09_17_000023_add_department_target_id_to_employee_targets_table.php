<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_targets', function (Blueprint $table) {
            $table->foreignId('department_target_id')
                ->nullable()
                ->after('annual_plan_id')
                ->constrained('department_targets')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('department_target_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_targets', function (Blueprint $table) {
            $table->dropForeign(['department_target_id']);
            $table->dropIndex(['department_target_id']);
            $table->dropColumn('department_target_id');
        });
    }
};
