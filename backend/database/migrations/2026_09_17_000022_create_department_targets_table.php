<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_plan_id')->constrained('department_plans')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('target_name', 255);
            $table->text('target_description')->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_plan_id');
            $table->index('department_id');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_targets');
    }
};
