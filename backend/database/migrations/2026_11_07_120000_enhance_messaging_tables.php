<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the existing internal-messaging tables (conversations,
     * conversation_participants, messages) with the columns the advanced
     * messaging module needs. Additive only — no table is duplicated and no
     * existing column is altered, so already-stored conversations survive.
     *
     * - conversations.description / icon : group description + profile icon.
     * - conversation_participants.role   : 'admin' (group creator/managers) or
     *   'member', driving group admin controls.
     * - conversation_participants.archived_at : per-user archive (one member
     *   archiving a thread never hides it from the other members).
     * - messages.reply_to_message_id     : quoted reply target.
     * - messages.client_token            : client-generated idempotency key so a
     *   double submit can never create two identical messages.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('subject');
            $table->string('icon', 16)->nullable()->after('description');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('user_id');
            $table->timestamp('archived_at')->nullable()->after('last_read_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Plain unsigned column rather than a constrained FK: SQLite cannot
            // add foreign keys to an existing table, and the index still keeps
            // the self-reference lookup cheap.
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('body');
            $table->string('client_token', 64)->nullable()->after('attachment_type');

            $table->index('reply_to_message_id');
            $table->unique('client_token');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['client_token']);
            $table->dropIndex(['reply_to_message_id']);
            $table->dropColumn(['reply_to_message_id', 'client_token']);
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['role', 'archived_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['description', 'icon']);
        });
    }
};
