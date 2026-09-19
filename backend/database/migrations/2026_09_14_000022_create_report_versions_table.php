<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('version_number');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('file_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'version_number']);
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
    }
};
