<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('period_type', 20)->default('monthly'); // monthly, quarterly, yearly
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open, closed, finalized
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_type', 'year', 'month', 'quarter'], 'uq_performance_period_boundary');
            $table->index(['period_type', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_periods');
    }
};
