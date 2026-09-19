<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'remarks')) {
                $table->text('remarks')->nullable()->after('description');
            }
            if (!Schema::hasColumn('reports', 'review_status')) {
                $table->string('review_status', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('reports', 'ai_processing_status')) {
                $table->string('ai_processing_status', 30)->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('reports', 'is_revision')) {
                $table->boolean('is_revision')->default(false)->after('version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['remarks', 'review_status', 'ai_processing_status', 'is_revision']);
        });
    }
};
