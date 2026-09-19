<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_validation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('ai_analysis_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('task_alignment_score', 5, 4)->nullable();
            $table->decimal('reference_alignment_score', 5, 4)->nullable();
            $table->decimal('completeness_score', 5, 4)->nullable();
            $table->decimal('relevance_score', 5, 4)->nullable();
            $table->decimal('evidence_score', 5, 4)->nullable();
            $table->decimal('clarity_score', 5, 4)->nullable();
            $table->decimal('overall_score', 5, 4)->nullable();
            $table->string('result', 20)->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('ai_analysis_id');
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_validation_results');
    }
};
