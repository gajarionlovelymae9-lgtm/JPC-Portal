<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('badge_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->json('criteria_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'badge_id', 'academic_year_id', 'period_start', 'period_end'],
                'uq_employee_badges_award'
            );
            $table->index('badge_id');
            $table->index('academic_year_id');
            $table->index('awarded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_badges');
    }
};
