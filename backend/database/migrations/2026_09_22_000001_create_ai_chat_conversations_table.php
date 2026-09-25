<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up() {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title')->nullable(); $table->text('context_summary')->nullable();
            $table->boolean('is_archived')->default(false); $table->timestamps(); $table->index('user_id');
        });
    }
    public function down() { Schema::dropIfExists('ai_chat_conversations'); }
};