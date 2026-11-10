<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target milestones — checkpoints along the way to target achievement.
 *
 * A milestone is achieved automatically once the parent target's effective
 * progress reaches its `threshold_percent`; the transition (and the date it
 * happened) is persisted so completion is auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_target_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_target_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('threshold_percent')->default(50);
            $table->string('status', 20)->default('pending');
            $table->timestamp('achieved_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('employee_target_id');
            $table->index(['employee_target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_target_milestones');
    }
};
