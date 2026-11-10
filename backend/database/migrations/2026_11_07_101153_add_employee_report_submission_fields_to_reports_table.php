<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'report_type')) {
                $table->string('report_type', 100)->nullable()->after('title');
            }

            if (! Schema::hasColumn('reports', 'reporting_period')) {
                $table->string('reporting_period', 120)->nullable()->after('report_type');
            }

            if (! Schema::hasColumn('reports', 'accomplishments')) {
                $table->longText('accomplishments')->nullable()->after('content');
            }

            if (! Schema::hasColumn('reports', 'completed_tasks_summary')) {
                $table->longText('completed_tasks_summary')->nullable()->after('accomplishments');
            }

            if (! Schema::hasColumn('reports', 'challenges_encountered')) {
                $table->longText('challenges_encountered')->nullable()->after('completed_tasks_summary');
            }

            if (! Schema::hasColumn('reports', 'next_plans')) {
                $table->longText('next_plans')->nullable()->after('challenges_encountered');
            }

            if (! Schema::hasColumn('reports', 'ai_summary')) {
                $table->longText('ai_summary')->nullable()->after('ai_status');
            }

            if (! Schema::hasColumn('reports', 'ai_summary_status')) {
                $table->string('ai_summary_status', 40)->nullable()->default('not_generated')->after('ai_summary');
            }

            if (! Schema::hasColumn('reports', 'ai_summary_generated_at')) {
                $table->timestamp('ai_summary_generated_at')->nullable()->after('ai_summary_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $columns = [
                'report_type',
                'reporting_period',
                'accomplishments',
                'completed_tasks_summary',
                'challenges_encountered',
                'next_plans',
                'ai_summary',
                'ai_summary_status',
                'ai_summary_generated_at',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('reports', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
