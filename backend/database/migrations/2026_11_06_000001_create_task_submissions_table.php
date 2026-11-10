<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_assignment_id')->constrained('task_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('method', 20);
            $table->string('url', 2048)->nullable();
            $table->text('written_response')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('task_id');
            $table->index('task_assignment_id');
            $table->index('submitted_by');
            $table->index(['task_assignment_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_submissions');
    }
};
