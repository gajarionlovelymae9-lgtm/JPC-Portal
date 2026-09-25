<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_scans', function (Blueprint $table) {
            $table->id();
            $table->string('scan_type', 30)->default('full'); // full, incremental, department, entity
            $table->json('scope')->nullable();
            $table->string('status', 30)->default('QUEUED');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('records_checked')->default(0);
            $table->unsignedInteger('issues_found')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('high_count')->default(0);
            $table->unsignedInteger('medium_count')->default(0);
            $table->unsignedInteger('low_count')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scan_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_scans');
    }
};
