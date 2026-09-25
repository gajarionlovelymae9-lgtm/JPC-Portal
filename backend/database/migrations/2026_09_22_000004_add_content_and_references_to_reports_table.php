<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }
            if (!Schema::hasColumn('reports', 'references')) {
                $table->longText('references')->nullable()->after('content');
            }
            if (!Schema::hasColumn('reports', 'ai_status')) {
                $table->string('ai_status', 50)->default('pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['content', 'references', 'ai_status']);
        });
    }
};
