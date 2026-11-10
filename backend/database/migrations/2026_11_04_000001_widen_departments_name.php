<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `departments.name` to match the validation limit.
 *
 * Full academic programme names (e.g. "Bachelor of Science in Civil
 * Engineering") fit in the old 150-character column, but the People
 * Management form is specified to accept names up to 255 characters. MySQL
 * 8 / InnoDB supports the existing unique index on a utf8mb4 varchar(255)
 * column, so no index changes are required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('name', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('name', 150)->change();
        });
    }
};
