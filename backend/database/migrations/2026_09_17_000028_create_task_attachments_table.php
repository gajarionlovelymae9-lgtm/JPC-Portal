<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('task_assignment_id')->nullable()->constrained('task_assignments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id');
            $table->index('task_assignment_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
