<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('analysis_type', 40);
            $table->string('provider', 100);
            $table->string('model', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('input_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('analysis_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
