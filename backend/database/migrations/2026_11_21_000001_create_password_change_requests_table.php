<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password change requests — an employee asks to change their password and an
 * administrator approves or rejects it before it takes effect.
 *
 * `proposed_password` stores the *hashed* new password. It is never applied at
 * request time; the account password only changes once an admin approves, at
 * which point the hash is copied straight onto the user row. Decisions are
 * appended to the existing append-only `activity_logs` trail by the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();

            $table->string('proposed_password');
            $table->text('reason')->nullable();

            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_change_requests');
    }
};
