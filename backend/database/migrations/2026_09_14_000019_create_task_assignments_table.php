<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('assigned_at')->nullable()->useCurrent();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('status', 30)->default('not_started');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'employee_id']);
            $table->index('employee_id');
            $table->index('status');
            $table->index('assigned_by');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
