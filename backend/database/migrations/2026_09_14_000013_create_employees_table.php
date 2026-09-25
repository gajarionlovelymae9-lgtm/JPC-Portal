<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('employee_number', 30)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('department_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('employment_status', 20)->default('active');
            $table->date('date_hired')->nullable();
            $table->date('date_ended')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('profile_photo', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');          // → employees_department_id_index
            $table->index('position_id');             // → employees_position_id_index
            $table->index('employment_status');       // → employees_employment_status_index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
