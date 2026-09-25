<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->nullable()->constrained('data_quality_scans')->nullOnDelete();
            $table->string('issue_type', 60);
            $table->string('severity', 20)->default('MEDIUM');
            $table->string('status', 20)->default('OPEN');
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description');
            $table->json('detected_value')->nullable();
            $table->json('expected_value')->nullable();
            $table->string('detected_by', 60)->default('automated_rule');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->timestamps();

            $table->index('issue_type');
            $table->index('severity');
            $table->index('status');
            $table->index(['entity_type', 'entity_id']);
            $table->index('department_id');
            $table->index('employee_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_issues');
    }
};
