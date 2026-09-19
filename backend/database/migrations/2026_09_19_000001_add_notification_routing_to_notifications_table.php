<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the custom notifications inbox with routing metadata used by the
     * deadline reminders, overdue alerts and management notifications.
     *
     * reminder_days stores the reminder threshold (from config/task.php) that a
     * reminder belongs to; overdue management alerts use the sentinel value -1.
     * The unique index makes the senders idempotent, so a scheduler re-run or a
     * duplicate dispatch can never create {user, type, task, reminder} twice.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->index();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->smallInteger('reminder_days')->nullable();

            $table->unique(
                ['user_id', 'type', 'task_id', 'reminder_days'],
                'notifications_user_type_task_reminder_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_type_task_reminder_unique');
            $table->dropColumn(['priority', 'task_id', 'reminder_days']);
        });
    }
};