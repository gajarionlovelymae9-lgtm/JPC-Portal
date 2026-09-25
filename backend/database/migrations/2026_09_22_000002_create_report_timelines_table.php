<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: Event Timeline Audit History for Reports.
     */
    public function up(): void
    {
        Schema::create('report_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained('report_versions')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50); // report_created, draft_updated, document_uploaded, submitted, ocr_completed, ai_analysis_completed, review_started, revision_requested, resubmitted, approved, rejected, cancelled
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_timelines');
    }
};
