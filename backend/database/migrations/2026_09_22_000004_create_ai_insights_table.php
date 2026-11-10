<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index('employee_id');
            $table->index('department_id');
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_insights');
    }
};
