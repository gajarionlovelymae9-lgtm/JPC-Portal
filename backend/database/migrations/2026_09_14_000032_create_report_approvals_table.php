<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('decision', 20);
            $table->text('comments')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('report_id');
            $table->index('approver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_approvals');
    }
};
