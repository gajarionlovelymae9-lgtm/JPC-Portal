<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('completion_rate', 5, 2)->nullable();
            $table->decimal('on_time_rate', 5, 2)->nullable();
            $table->unsignedInteger('approved_reports')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->integer('rank')->nullable();
            $table->boolean('is_employee_of_month')->default(false);
            $table->timestamps();

            $table->unique(
                ['employee_id', 'academic_year_id', 'month', 'year'],
                'uq_monthly_achievements_period'
            );
            $table->index(['academic_year_id', 'month', 'year']);
            $table->index('is_employee_of_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_achievements');
    }
};
