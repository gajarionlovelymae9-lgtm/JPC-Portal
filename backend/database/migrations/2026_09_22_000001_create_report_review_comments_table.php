<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: Structured Reviewer Comments on Report Sections.
     */
    public function up(): void
    {
        Schema::create('report_review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained('report_versions')->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('section_key', 50)->default('general'); // objectives, activities, results, challenges, recommendations, references, general
            $table->text('comment');
            $table->string('status', 20)->default('open'); // open, resolved
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['report_id', 'status']);
            $table->index('section_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_review_comments');
    }
};
