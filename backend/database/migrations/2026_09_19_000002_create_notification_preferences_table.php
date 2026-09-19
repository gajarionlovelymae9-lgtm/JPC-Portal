<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user notification preferences (in-app bell + email channels).
     *
     * user_id              -> the recipient this preference belongs to.
     * event                -> a NotificationEvent value ("report_submitted",
     *                           "task_deadline", "achievement_earned", ...).
     * in_app_enabled       -> whether the in-app inbox/bell may send this event.
     * email_enabled        -> whether an email may be sent for this event.
     * critical_override_on -> set by NotificationPreferenceService when the
     *                           event is institutionally critical, forcing the
     *                           channels back on regardless of the user toggles
     *                           (notification policy cannot be fully disabled).
     * The (user_id, event) unique index guarantees at most one preference row
     * per event per user, keeping upserts idempotent under retries.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('critical_override_on')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
