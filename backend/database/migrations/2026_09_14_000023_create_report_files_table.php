<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('report_version_id')->constrained('report_versions')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('storage_disk', 50)->default('public');
            $table->string('file_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->index('report_id');
            $table->index('report_version_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_files');
    }
};
