<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target adjustment requests — an employee asks to change an assigned target;
 * an Admin (or HR) approves or rejects.
 *
 * `original_snapshot` stores the target's values *before* an approved change
 * is applied, so applying an adjustment never loses the original target
 * information. History / decisions are appended to `activity_logs` via
 * ActivityLogger (the existing append-only trail), so no extra table is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_target_adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_target_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();

            $table->text('reason');
            $table->string('request_type', 30)->default('other');

            $table->decimal('proposed_target_value', 12, 2)->nullable();
            $table->string('proposed_target_unit', 50)->nullable();
            $table->date('proposed_start_date')->nullable();
            $table->date('proposed_end_date')->nullable();
            $table->text('proposed_description')->nullable();

            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('original_snapshot')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('employee_target_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_target_adjustment_requests');
    }
};
