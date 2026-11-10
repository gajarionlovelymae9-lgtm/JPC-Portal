<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the existing `department_escalations` table with the fields the
     * full escalation workflow needs (subject employee, related record,
     * requested action, optional attachment, assigned/reviewed administrators)
     * and normalise the legacy status/type values onto the canonical enums.
     *
     * Existing rows are preserved: `open` becomes `pending`, `closed` becomes
     * `resolved`, and the legacy issue types are mapped to the EscalationType
     * values. Nothing is dropped.
     */
    public function up(): void
    {
        Schema::table('department_escalations', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('department_id')
                ->constrained('employees')->nullOnDelete();
            $table->string('related_type', 60)->nullable()->after('type');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->text('requested_action')->nullable()->after('reason');
            $table->string('attachment_path')->nullable()->after('requested_action');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->foreignId('assigned_to')->nullable()->after('priority')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->foreignId('reviewed_by')->nullable()->after('assigned_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index('priority');
            $table->index('assigned_to');
            $table->index(['related_type', 'related_id']);
        });

        // Normalise the legacy lifecycle values onto the canonical statuses.
        DB::table('department_escalations')->where('status', 'open')->update(['status' => 'pending']);
        DB::table('department_escalations')->where('status', 'closed')->update(['status' => 'resolved']);
        DB::table('department_escalations')->whereNotIn('status', ['pending', 'under_review', 'action_required', 'resolved', 'rejected'])
            ->update(['status' => 'pending']);

        // Normalise the legacy issue-type values onto the canonical enum.
        DB::table('department_escalations')->where('type', 'overdue_task')->update(['type' => 'unresolved_overdue_task']);
        DB::table('department_escalations')->where('type', 'repeated_revision')->update(['type' => 'repeated_late_submission']);
        DB::table('department_escalations')->where('type', 'missing_report')->update(['type' => 'missing_required_report']);
        DB::table('department_escalations')->where('type', 'target_risk')->update(['type' => 'target_at_risk']);
        DB::table('department_escalations')->where('type', 'general')->update(['type' => 'other']);
        DB::table('department_escalations')->whereNotIn('type', ['repeated_late_submission', 'unresolved_overdue_task', 'missing_required_report', 'target_at_risk', 'other'])
            ->update(['type' => 'other']);

        // Normalise the legacy priority values onto the canonical enum.
        DB::table('department_escalations')->where('priority', 'urgent')->update(['priority' => 'critical']);
        DB::table('department_escalations')->whereNotIn('priority', ['low', 'medium', 'high', 'critical'])
            ->update(['priority' => 'medium']);

        Schema::table('department_escalations', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
            $table->string('type', 50)->default('other')->change();
            $table->string('priority', 20)->default('medium')->change();
        });
    }

    public function down(): void
    {
        Schema::table('department_escalations', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['related_type', 'related_id']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['assigned_to']);
            $table->dropColumn([
                'employee_id',
                'related_type',
                'related_id',
                'requested_action',
                'attachment_path',
                'attachment_name',
                'assigned_to',
                'assigned_at',
                'reviewed_by',
                'reviewed_at',
            ]);
        });
    }
};
