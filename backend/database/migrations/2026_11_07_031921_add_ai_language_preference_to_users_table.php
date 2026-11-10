<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'ai_language_preference')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('ai_language_preference', 20)->default('auto')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'ai_language_preference')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_language_preference');
        });
    }
};
