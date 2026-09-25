<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_versions', function (Blueprint $table) {
            $table->string('title', 500)->nullable()->after('version_number');
            $table->longText('content')->nullable()->after('title');
            $table->longText('references')->nullable()->after('content');
            $table->string('status', 50)->default('submitted')->after('references');
            $table->text('change_summary')->nullable()->after('notes');
            $table->json('metadata')->nullable()->after('change_summary');
        });
    }

    public function down(): void
    {
        Schema::table('report_versions', function (Blueprint $table) {
            $table->dropColumn(['title', 'content', 'references', 'status', 'change_summary', 'metadata']);
        });
    }
};
