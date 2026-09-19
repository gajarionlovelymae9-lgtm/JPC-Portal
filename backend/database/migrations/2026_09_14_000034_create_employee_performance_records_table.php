<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('period_type', 20)->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('completion_rate', 5, 2)->nullable();
            $table->decimal('on_time_rate', 5, 2)->nullable();
            $table->decimal('approval_rate', 5, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('target_achievement_rate', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('performance_status', 20)->default('consistent');
            $table->timestamps();

            $table->unique(
                ['employee_id', 'academic_year_id', 'period_type', 'period_start', 'period_end'],
                'uq_employee_performance_records_period'
            );
            $table->index('academic_year_id');
            $table->index('period_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance_records');
    }
};
