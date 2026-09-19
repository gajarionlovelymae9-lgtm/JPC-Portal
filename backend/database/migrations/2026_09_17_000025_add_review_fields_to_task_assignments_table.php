<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->timestamp('accepted_at')
                ->nullable()
                ->after('assigned_at');

            $table->text('remarks')
                ->nullable()
                ->after('status');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'remarks', 'deleted_at']);
        });
    }
};
