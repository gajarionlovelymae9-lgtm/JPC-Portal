<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_reference_id')->nullable()->constrained('task_references')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('reference_text')->nullable();
            $table->string('reference_url', 500)->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('task_reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_references');
    }
};
