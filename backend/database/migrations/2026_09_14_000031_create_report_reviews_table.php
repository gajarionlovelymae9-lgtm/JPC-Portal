<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('review_type', 20)->default('initial');
            $table->string('decision', 30);
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('reviewer_id');
            $table->index('review_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reviews');
    }
};
