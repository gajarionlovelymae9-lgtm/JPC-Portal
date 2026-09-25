<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_performance_records', function (Blueprint $table) {
            $table->foreignId('performance_period_id')->nullable()->after('academic_year_id')->constrained('performance_periods')->nullOnDelete();
            $table->decimal('report_completion_rate', 5, 2)->nullable()->after('completion_rate');
            $table->decimal('productivity_score', 5, 2)->nullable()->after('overall_score');
            $table->string('rating', 30)->nullable()->after('productivity_score');
            $table->string('calculation_version', 20)->default('v1.0')->after('rating');
            $table->json('breakdown_data')->nullable()->after('calculation_version');
            $table->json('metadata')->nullable()->after('breakdown_data');
            $table->timestamp('calculated_at')->nullable()->after('metadata');

            $table->index('performance_period_id');
            $table->index('rating');
        });

        Schema::create('performance_score_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_record_id')->constrained('employee_performance_records')->cascadeOnDelete();
            $table->string('metric_key', 50);
            $table->string('metric_label', 100);
            $table->decimal('raw_value', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('weighted_value', 5, 2)->default(0);
            $table->string('calculation_version', 20)->default('v1.0');
            $table->timestamps();

            $table->index(['performance_record_id', 'metric_key'], 'idx_breakdown_record_metric');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_score_breakdowns');

        Schema::table('employee_performance_records', function (Blueprint $table) {
            $table->dropForeign(['performance_period_id']);
            $table->dropColumn([
                'performance_period_id',
                'report_completion_rate',
                'productivity_score',
                'rating',
                'calculation_version',
                'breakdown_data',
                'metadata',
                'calculated_at',
            ]);
        });
    }
};
