<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_plan_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('target_unit', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('annual_plan_id');
            $table->index('employee_id');
            $table->index('status');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_targets');
    }
};
