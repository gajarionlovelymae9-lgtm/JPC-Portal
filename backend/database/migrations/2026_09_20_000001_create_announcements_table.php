<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System-wide and targeted announcements table.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('message');
            $table->string('priority', 20)->default('normal'); // low, normal, high, critical
            $table->string('audience_type', 30)->default('all'); // all, department, role, user
            $table->string('audience_id', 100)->nullable(); // department_id, role_name, user_id
            $table->string('status', 20)->default('draft'); // draft, scheduled, published, expired
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['audience_type', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
