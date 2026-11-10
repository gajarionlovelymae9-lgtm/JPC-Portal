<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target breakdown — a target split into smaller sub-targets.
 *
 * Each row is one sub-target with its own individually tracked progress.
 * The parent target's effective progress is the weighted average of its
 * breakdowns (see App\Services\Employee\EmployeeTargetService), which is how
 * sub-target progress is connected back to the main target.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_target_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_target_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('target_unit', 50)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('employee_target_id');
            $table->index(['employee_target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_target_breakdowns');
    }
};
