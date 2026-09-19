<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_tasks')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->unsignedInteger('pending_tasks')->default(0);
            $table->unsignedInteger('overdue_tasks')->default(0);
            $table->unsignedInteger('total_reports')->default(0);
            $table->unsignedInteger('submitted_reports')->default(0);
            $table->unsignedInteger('approved_reports')->default(0);
            $table->unsignedInteger('late_reports')->default(0);
            $table->decimal('completion_rate', 5, 2)->nullable();
            $table->decimal('on_time_rate', 5, 2)->nullable();
            $table->decimal('approval_rate', 5, 2)->nullable();
            $table->decimal('target_achievement_rate', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('academic_year_id');
            $table->index('snapshot_date');
            $table->index(['employee_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_progress_snapshots');
    }
};
