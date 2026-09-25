<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->date('performed_at');
            $table->string('version', 20)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('maintenance_type', 60)->index();
            $table->string('affected_module', 120)->nullable();
            $table->text('description');
            $table->string('result', 20)->default('completed'); // completed | failed | pending | rolled_back
            $table->boolean('rollback_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['performed_at', 'maintenance_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};