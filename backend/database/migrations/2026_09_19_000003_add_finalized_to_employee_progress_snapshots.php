<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_progress_snapshots', function (Blueprint $table) {
            $table->boolean('finalized')->default(false)->after('overall_score');
            $table->foreignId('finalized_by')->nullable()->after('finalized')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });
    }

    public function down(): void
    {
        Schema::table('employee_progress_snapshots', function (Blueprint $table) {
            $table->dropColumn(['finalized', 'finalized_by', 'finalized_at']);
        });
    }
};
