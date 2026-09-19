<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.3-K additive columns on the existing AI report pipeline tables.
 *
 *  - reports.remarks                free-text remarks captured at upload time
 *  - ai_analyses.report_version_id  links an analysis to a specific version so
 *                                   every version keeps independent analysis
 *  - ai_analyses.analysis_version   AI engine version marker (v1.0)
 *  - ai_validation_results.reference_anchor_score / confidence_score / requirement_match
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('description');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->string('analysis_version', 20)->nullable()->after('model');
            $table->foreignId('report_version_id')
                ->nullable()
                ->after('report_id')
                ->constrained('report_versions')
                ->nullOnDelete();
        });

        Schema::table('ai_validation_results', function (Blueprint $table) {
            $table->decimal('reference_anchor_score', 5, 4)->nullable()->after('reference_alignment_score');
            $table->string('requirement_match', 30)->nullable()->after('reference_anchor_score');
            $table->decimal('confidence_score', 5, 4)->nullable()->after('overall_score');
        });

        Schema::table('ai_summaries', function (Blueprint $table) {
            $table->json('key_activities')->nullable()->after('key_accomplishments');
            $table->json('results')->nullable()->after('key_findings');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_version_id');
            $table->dropColumn('analysis_version');
        });

        Schema::table('ai_validation_results', function (Blueprint $table) {
            $table->dropColumn(['reference_anchor_score', 'requirement_match', 'confidence_score']);
        });

        Schema::table('ai_summaries', function (Blueprint $table) {
            $table->dropColumn(['key_activities', 'results']);
        });
    }
};