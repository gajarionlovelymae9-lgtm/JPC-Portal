<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3.2-B — Employee Management.
     *
     * New append-only change log: preserves old/new values for employee profile
     * field changes (department, position, employment_status, employment_type,
     * supervisor, dates, contact info, etc.).
     *
     * Hard-delete behavior: employee_id RESTRICT (an employee with history can
     * never be hard-deleted); changed_by NULL ON DELETE (the historical record
     * survives removal of the actor's user account).
     */
    public function up(): void
    {
        Schema::create('employee_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('field_name', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index('employee_id', 'idx_employee_histories_employee_id');
            $table->index('field_name', 'idx_employee_histories_field_name');
            $table->index('effective_date', 'idx_employee_histories_effective_date');
            $table->index(['employee_id', 'field_name'], 'idx_employee_histories_employee_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_histories');
    }
};
