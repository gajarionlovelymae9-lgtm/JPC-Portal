<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_processings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_file_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('provider', 100);
            $table->string('status', 20)->default('pending');
            $table->string('language', 20)->nullable();
            $table->longText('raw_text')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('report_file_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_processings');
    }
};
