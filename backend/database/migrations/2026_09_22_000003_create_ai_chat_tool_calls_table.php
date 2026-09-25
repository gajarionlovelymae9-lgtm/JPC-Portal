<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up() {
        Schema::create('ai_chat_tool_calls', function (Blueprint $table) {
            $table->id(); $table->foreignId('ai_chat_message_id')->constrained('ai_chat_messages')->onDelete('cascade');
            $table->string('tool_name'); $table->json('parameters')->nullable(); $table->json('result')->nullable();
            $table->timestamp('executed_at')->nullable(); $table->timestamps(); $table->index('ai_chat_message_id');
        });
    }
    public function down() { Schema::dropIfExists('ai_chat_tool_calls'); }
};