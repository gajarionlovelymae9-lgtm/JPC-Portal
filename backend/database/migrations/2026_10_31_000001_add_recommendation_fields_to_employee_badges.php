<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department Head recognition recommendations.
 *
 * A recommendation is not a second recognition system: it is a pending
 * employee_badges award that carries the recommending head and the supporting
 * details, so HR reviews it through the existing verify/reject workflow with
 * no extra tables or statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_badges', function (Blueprint $table) {
            $table->foreignId('recommended_by')->nullable()->after('criteria_snapshot')
                ->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable()->after('recommended_by');
            $table->text('recommendation_details')->nullable()->after('recommended_at');

            $table->index('recommended_by');
        });
    }

    public function down(): void
    {
        Schema::table('employee_badges', function (Blueprint $table) {
            if (Schema::hasColumn('employee_badges', 'recommended_by')) {
                $table->dropForeign(['recommended_by']);
                $table->dropIndex(['recommended_by']);
                $table->dropColumn(['recommended_by', 'recommended_at', 'recommendation_details']);
            }
        });
    }
};
