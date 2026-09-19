<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('reference_type', 20)->default('document');
            $table->string('reference_url', 500)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id');
            $table->index('reference_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_references');
    }
};
