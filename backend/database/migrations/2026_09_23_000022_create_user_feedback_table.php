<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40)->index(); // bug | incorrect_information | ui_problem | ai_concern | ocr_concern | feature_request | usability | other
            $table->string('subject', 255);
            $table->text('description');
            $table->string('priority', 20)->default('low')->index(); // low | medium | high | critical
            $table->string('status', 20)->default('OPEN')->index(); // OPEN | UNDER_REVIEW | IN_PROGRESS | RESOLVED | REJECTED | CLOSED
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};