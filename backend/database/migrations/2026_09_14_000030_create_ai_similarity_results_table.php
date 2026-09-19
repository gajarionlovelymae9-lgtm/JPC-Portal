<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_similarity_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('compared_report_id')->constrained('reports')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('similarity_score', 5, 4)->default(0);
            $table->string('status', 30)->default('normal');
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'compared_report_id']);
            $table->index('compared_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_similarity_results');
    }
};
