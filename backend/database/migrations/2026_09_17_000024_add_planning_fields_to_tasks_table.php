<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('department_plan_id')
                ->nullable()
                ->after('annual_plan_id')
                ->constrained('department_plans')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('department_target_id')
                ->nullable()
                ->after('department_plan_id')
                ->constrained('department_targets')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('employee_target_id')
                ->nullable()
                ->after('department_target_id')
                ->constrained('employee_targets')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('parent_task_id')
                ->nullable()
                ->after('employee_target_id')
                ->constrained('tasks')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('task_code', 50)
                ->nullable()
                ->after('id');

            $table->decimal('estimated_hours', 8, 2)
                ->nullable()
                ->after('due_date');

            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        DB::statement(
            "UPDATE tasks
             SET task_code = CONCAT('TASK-', YEAR(COALESCE(created_at, NOW())), '-', LPAD(id, 4, '0'))
             WHERE task_code IS NULL",
        );

        Schema::table('tasks', function (Blueprint $table) {
            $table->unique('task_code');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['task_code']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['employee_target_id']);
            $table->dropForeign(['department_target_id']);
            $table->dropForeign(['department_plan_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'task_code',
                'department_plan_id',
                'department_target_id',
                'employee_target_id',
                'parent_task_id',
                'estimated_hours',
                'updated_by',
            ]);
        });
    }
};
