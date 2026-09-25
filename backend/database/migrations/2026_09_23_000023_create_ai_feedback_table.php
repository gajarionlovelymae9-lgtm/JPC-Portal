<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ai_type', 40)->index(); // summary | validation | similarity | findings | insight | chat
            $table->unsignedBigInteger('ai_record_id')->nullable();
            $table->string('category', 30)->default('quality_feedback'); // quality_feedback | incorrect | partially_correct | needs_human_review
            $table->string('verdict', 40)->index(); // Correct | Partially Correct | Incorrect | Needs Human Review
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['ai_type', 'ai_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};