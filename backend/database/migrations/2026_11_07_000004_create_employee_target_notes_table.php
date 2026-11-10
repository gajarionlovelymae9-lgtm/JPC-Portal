<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target achievement notes — free-text notes an employee attaches to their
 * own target accomplishments. Only the author may edit or delete a note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_target_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_target_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->text('body');
            $table->timestamps();

            $table->index('employee_target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_target_notes');
    }
};
