<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('submission_date')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id');
            $table->index('employee_id');
            $table->index('status');
            $table->index('submitted_at');
            $table->index('is_late');
            $table->index('deadline');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
