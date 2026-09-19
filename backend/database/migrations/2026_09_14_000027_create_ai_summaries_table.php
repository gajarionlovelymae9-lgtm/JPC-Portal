<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('ai_analysis_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->longText('summary');
            $table->json('key_accomplishments')->nullable();
            $table->json('key_findings')->nullable();
            $table->json('issues')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('action_items')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('ai_analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
