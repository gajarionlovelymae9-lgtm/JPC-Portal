<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_assignment_id')->nullable()->constrained('task_assignments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id');
            $table->index('task_assignment_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
