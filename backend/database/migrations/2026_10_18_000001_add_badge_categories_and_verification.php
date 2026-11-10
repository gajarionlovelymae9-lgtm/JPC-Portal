<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only schema evolution for the Admin Achievement & Badges module.
 *
 * No existing column is dropped or rewritten:
 *  - badges.category / badges.allow_repeats are new, nullable/defaulted columns
 *    so every existing badge row keeps its meaning.
 *  - employee_badges.verification_* columns default to 'verified' so historical
 *    awards made by AchievementEvaluationService stay valid official records;
 *    awards created from now on may start life as 'pending' until an admin
 *    verifies or rejects them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('icon');
            $table->boolean('allow_repeats')->default(false)->after('is_active');
            $table->index('category');
        });

        Schema::table('employee_badges', function (Blueprint $table) {
            $table->string('verification_status', 16)->default('verified')->after('criteria_snapshot');
            $table->foreignId('verified_by')->nullable()->after('verification_status')
                ->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
            $table->text('evidence')->nullable()->after('rejection_reason');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('employee_badges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropIndex(['verification_status']);
            $table->dropColumn(['verification_status', 'verified_at', 'rejection_reason', 'evidence']);
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'allow_repeats']);
        });
    }
};
