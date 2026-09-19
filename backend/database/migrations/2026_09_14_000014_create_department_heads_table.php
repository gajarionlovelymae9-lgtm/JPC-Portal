<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index('department_id');
            $table->index('employee_id');
            $table->index('is_current');
        });

        // Generated stored column: enforces at most one current head per department.
        // DB::statement(
        //     'ALTER TABLE `department_heads` ADD COLUMN `current_department_id` BIGINT UNSIGNED '.
        //     'GENERATED ALWAYS AS (IF(`is_current`, `department_id`, NULL)) STORED'
        // );
        // DB::statement(
        //     'CREATE UNIQUE INDEX `uq_department_heads_current` ON `department_heads` (`current_department_id`)'
        // );
    }

    public function down(): void
    {
        Schema::dropIfExists('department_heads');
    }
};
