<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_assignment_id')->constrained('task_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('previous_percentage', 5, 2)->default(0);
            $table->decimal('new_percentage', 5, 2)->default(0);
            $table->string('status', 30)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index('task_id');
            $table->index('task_assignment_id');
            $table->index('employee_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_progress');
    }
};
