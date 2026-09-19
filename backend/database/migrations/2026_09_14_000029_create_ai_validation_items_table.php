<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_validation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_validation_result_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('category', 40);
            $table->string('severity', 20)->default('info');
            $table->text('finding')->nullable();
            $table->text('evidence')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();

            $table->index('ai_validation_result_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_validation_items');
    }
};
