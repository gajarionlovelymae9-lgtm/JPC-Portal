<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_issue_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_quality_issue_id')->constrained('data_quality_issues')->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->string('action', 50); // reviewed, confirmed, dismissed, resolved, reopened
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('data_quality_issue_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_issue_histories');
    }
};
