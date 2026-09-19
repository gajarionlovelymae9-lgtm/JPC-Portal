<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_plan_id')->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('target_unit', 50)->nullable();
            $table->text('required_output')->nullable();
            $table->string('status', 30)->default('not_started');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('annual_plan_id');
            $table->index('department_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('due_date');
            $table->index('priority');
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
