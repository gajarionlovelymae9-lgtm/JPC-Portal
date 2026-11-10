<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for every escalation event: creation, review,
     * assignment, status changes, resolution and internal notes.
     *
     * `is_internal` marks notes written by Administration that must never be
     * exposed to the originating Department Head.
     */
    public function up(): void
    {
        Schema::create('escalation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalation_id')->constrained('department_escalations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->timestamps();

            $table->index('escalation_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_histories');
    }
};
