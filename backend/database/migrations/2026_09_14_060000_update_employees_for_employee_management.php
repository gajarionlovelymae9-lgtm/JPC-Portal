<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3.2-B — Employee Management.
     *
     * Extends the existing Phase 3.1 `employees` table (kept as source of truth)
     * with the approved Phase 3.2-A design:
     *   - ADD  employment_type, date_of_birth, gender, address
     *   - ADD  supervisor_id (self-referencing FK)
     *   - RENAME profile_photo -> profile_photo_path (storage-path semantics)
     *   - ADD  indexes (employment_type, supervisor_id, date_hired, name)
     *
     * Backward compatibility: purely additive + one safe rename. The renamed
     * column `profile_photo_path` is not referenced anywhere yet (file storage
     * ships in a later phase), so the rename breaks no existing code/seeders.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 20)->default('regular');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('employees')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->renameColumn('profile_photo', 'profile_photo_path');

            $table->index('employment_type', 'idx_employees_employment_type');
            $table->index('supervisor_id', 'idx_employees_supervisor_id');
            $table->index('date_hired', 'idx_employees_date_hired');
            $table->index(['last_name', 'first_name'], 'idx_employees_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropIndex('idx_employees_supervisor_id');
            $table->dropIndex('idx_employees_employment_type');
            $table->dropIndex('idx_employees_date_hired');
            $table->dropIndex('idx_employees_name');
            $table->dropColumn(['employment_type', 'date_of_birth', 'gender', 'address', 'supervisor_id']);
            $table->renameColumn('profile_photo_path', 'profile_photo');
        });
    }
};
