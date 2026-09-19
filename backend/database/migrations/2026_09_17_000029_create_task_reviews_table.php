<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_assignment_id')->constrained('task_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('decision', 30);
            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->index('task_assignment_id');
            $table->index('reviewed_by');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reviews');
    }
};
